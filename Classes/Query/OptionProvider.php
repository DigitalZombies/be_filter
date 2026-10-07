<?php

declare(strict_types=1);

namespace DigitalZombies\BeFilter\Query;

use DigitalZombies\BeFilter\Event\ModifyFilterQueryEvent;
use DigitalZombies\BeFilter\Filter\Filter;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Schema\Capability\TcaSchemaCapability;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;

/**
 * Selectable values of a filter: static items, records of the foreign table, or both.
 * A filter declaring "dependsOn" only offers values that occur under its parents.
 */
final readonly class OptionProvider implements ReachableValueLookup
{
    private const OPTION_LIMIT = 50;

    public function __construct(
        private ConnectionPool $connectionPool,
        private ConstraintBuilder $constraintBuilder,
        private TcaSchemaFactory $tcaSchemaFactory,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    /**
     * The first {@see Filter::$optionLimit} options and how many there are in total.
     *
     * @param array<string, Filter> $filters
     * @param array<string, mixed> $values Normalized values of all filters
     * @return array{options: array<int, array{value: string, label: string}>, total: int}
     */
    public function getOptions(Filter $filter, array $filters, array $values, string $search, int $pageId): array
    {
        $limit = $filter->optionLimit > 0 ? $filter->optionLimit : self::OPTION_LIMIT;
        $parents = array_intersect_key($filters, array_flip($filter->dependsOn));
        $parentValues = array_intersect_key($values, $parents);

        $foreignTable = $filter->getForeignTable();
        $readable = $foreignTable !== null && $this->isReadable($foreignTable);
        // A narrowed filter asks its own table which values occur, which is one query
        // with a join instead of a list of every value that exists.
        $joined = $parentValues !== [] && $readable && $this->canJoin($filter, (string)$foreignTable);

        // Static items are matched in PHP, their number is the number of items in the TCA.
        $available = $parentValues !== [] && (!$joined || $filter->getItems() !== [])
            ? $this->getAvailableValues($filter, $parents, $parentValues, $pageId)
            : null;
        if ($available === [] && !$joined) {
            return ['options' => [], 'total' => 0];
        }

        $options = $this->getItemOptions($filter, $search, $available, $pageId);
        $total = count($options);

        if ($readable) {
            $records = $joined
                ? $this->getNarrowedRecordOptions($filter, (string)$foreignTable, $parents, $parentValues, $search, $pageId, $limit)
                : $this->getRecordOptions($filter, (string)$foreignTable, $search, $available, $limit);
            $options = array_merge($options, $records);

            // A list that is not full hides nothing, counting would only cost a query.
            if (count($records) < $limit) {
                $total += count($records);
            } else {
                $total += $joined
                    ? $this->countNarrowedRecordOptions($filter, (string)$foreignTable, $parents, $parentValues, $search, $pageId)
                    : $this->countRecordOptions($filter, (string)$foreignTable, $search, $available);
            }
        }

        return ['options' => array_slice($options, 0, $limit), 'total' => $total];
    }

    /**
     * A comma separated field cannot be joined portably, and a self relation would need
     * an alias the scoping listeners do not know about.
     */
    private function canJoin(Filter $filter, string $foreignTable): bool
    {
        return $filter->table !== $foreignTable && ($filter->getMmTable() !== null || !$filter->isMultiValue());
    }

    /**
     * @param array<string, Filter> $parents
     * @param array<string, mixed> $parentValues
     * @return array<int, array{value: string, label: string}>
     */
    private function getNarrowedRecordOptions(
        Filter $filter,
        string $foreignTable,
        array $parents,
        array $parentValues,
        string $search,
        int $pageId,
        int $limit
    ): array {
        $labelFields = $this->getLabelFields($foreignTable);
        $queryBuilder = $this->narrowedQuery($filter, $foreignTable, $parents, $parentValues, $search, $pageId, $labelFields);
        $queryBuilder->select($foreignTable . '.*')->distinct()->setMaxResults($limit);
        foreach ($labelFields as $labelField) {
            $queryBuilder->addOrderBy($foreignTable . '.' . $labelField);
        }

        $options = [];
        foreach ($queryBuilder->executeQuery()->fetchAllAssociative() as $row) {
            $options[] = [
                'value' => (string)$row['uid'],
                'label' => BackendUtility::getRecordTitle($foreignTable, $row) ?: '#' . $row['uid'],
            ];
        }

        return $options;
    }

    /**
     * @param array<string, Filter> $parents
     * @param array<string, mixed> $parentValues
     */
    private function countNarrowedRecordOptions(
        Filter $filter,
        string $foreignTable,
        array $parents,
        array $parentValues,
        string $search,
        int $pageId
    ): int {
        $queryBuilder = $this->narrowedQuery(
            $filter,
            $foreignTable,
            $parents,
            $parentValues,
            $search,
            $pageId,
            $this->getLabelFields($foreignTable)
        );
        $queryBuilder->selectLiteral('COUNT(DISTINCT ' . $queryBuilder->quoteIdentifier($foreignTable . '.uid') . ')');

        return (int)$queryBuilder->executeQuery()->fetchOne();
    }

    /**
     * Both tables are queried under their own name, so the listeners of
     * {@see ModifyFilterQueryEvent} constrain the one they know.
     *
     * @param array<string, Filter> $parents
     * @param array<string, mixed> $parentValues
     * @param string[] $labelFields
     */
    private function narrowedQuery(
        Filter $filter,
        string $foreignTable,
        array $parents,
        array $parentValues,
        string $search,
        int $pageId,
        array $labelFields
    ): QueryBuilder {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($filter->table);
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $queryBuilder
            ->from($filter->table)
            ->where(
                $queryBuilder->expr()->eq(
                    $filter->table . '.pid',
                    $queryBuilder->createNamedParameter($pageId, Connection::PARAM_INT)
                )
            );
        $this->constraintBuilder->apply($queryBuilder, $parents, $parentValues);

        $mmTable = $filter->getMmTable();
        if ($mmTable !== null) {
            [$recordColumn, $valueColumn] = $filter->hasOppositeMmSide()
                ? ['uid_foreign', 'uid_local']
                : ['uid_local', 'uid_foreign'];
            $queryBuilder->join(
                $filter->table,
                $mmTable,
                'mm',
                $queryBuilder->expr()->eq('mm.' . $recordColumn, $queryBuilder->quoteIdentifier($filter->table . '.uid'))
            );
            foreach ($filter->getMmMatchFields() as $matchField => $matchValue) {
                $queryBuilder->andWhere(
                    $queryBuilder->expr()->eq('mm.' . $matchField, $queryBuilder->createNamedParameter($matchValue))
                );
            }
            $queryBuilder->join(
                'mm',
                $foreignTable,
                $foreignTable,
                $queryBuilder->expr()->eq($foreignTable . '.uid', $queryBuilder->quoteIdentifier('mm.' . $valueColumn))
            );
        } else {
            $queryBuilder->join(
                $filter->table,
                $foreignTable,
                $foreignTable,
                $queryBuilder->expr()->eq(
                    $foreignTable . '.uid',
                    $queryBuilder->quoteIdentifier($filter->table . '.' . $filter->name)
                )
            );
        }

        if ($search !== '') {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->or(...$this->getSearchConstraints($queryBuilder, $labelFields, $search, $foreignTable))
            );
        }

        $this->eventDispatcher->dispatch(new ModifyFilterQueryEvent($queryBuilder, $filter->table, $filter));
        $this->eventDispatcher->dispatch(new ModifyFilterQueryEvent($queryBuilder, $foreignTable, $filter));

        return $queryBuilder;
    }

    /**
     * Labels of the given values, used to render the current selection.
     *
     * @param string[] $values
     * @return array<string, string>
     */
    public function getLabels(Filter $filter, array $values, int $pageId = 0): array
    {
        if ($values === []) {
            return [];
        }

        $labels = $this->resolveLabels($filter, $values, $pageId);

        return array_combine($values, array_map(static fn(string $value): string => $labels[$value] ?? $value, $values));
    }

    /**
     * Of the given values, the ones that still name something this user may see. A saved
     * filter travels between users, and a record it names can be out of their reach.
     *
     * @param string[] $values
     * @return string[]
     */
    public function getReachableValues(Filter $filter, array $values, int $pageId = 0): array
    {
        if ($values === []) {
            return [];
        }

        $reachable = $this->resolveLabels($filter, $values, $pageId);

        return array_values(array_filter($values, static fn(string $value): bool => isset($reachable[$value])));
    }

    /**
     * The labels of those values that resolve, which is what the user is allowed to see:
     * the queries behind it carry the constraints of {@see ModifyFilterQueryEvent}.
     *
     * @param string[] $values
     * @return array<string, string>
     */
    private function resolveLabels(Filter $filter, array $values, int $pageId): array
    {
        $labels = [];
        foreach ($this->getItemOptions($filter, '', $values, $pageId) as $option) {
            $labels[$option['value']] = $option['label'];
        }
        $foreignTable = $filter->getForeignTable();
        if ($foreignTable !== null && $this->isReadable($foreignTable)) {
            foreach ($this->getRecordOptions($filter, $foreignTable, '', $values, count($values)) as $option) {
                $labels[$option['value']] = $option['label'];
            }
        }

        return $labels;
    }

    /**
     * @param string[]|null $available
     * @return array<int, array{value: string, label: string}>
     */
    private function getItemOptions(Filter $filter, string $search, ?array $available, int $pageId): array
    {
        $tsConfig = $this->getColumnTsConfig($filter, $pageId);
        $keep = GeneralUtility::trimExplode(',', (string)($tsConfig['keepItems'] ?? ''), true);
        $remove = GeneralUtility::trimExplode(',', (string)($tsConfig['removeItems'] ?? ''), true);
        $altLabels = (array)($tsConfig['altLabels.'] ?? []);

        $options = [];
        foreach ($filter->getItems() as $item) {
            $value = (string)$item['value'];
            if (($keep !== [] && !in_array($value, $keep, true)) || in_array($value, $remove, true)) {
                // An item the page configuration takes away is nothing to filter by.
                continue;
            }
            $label = $this->getLanguageService()->sL((string)($altLabels[$value] ?? $item['label'] ?? '')) ?: $value;
            if (!$this->matches($label, $search) || ($available !== null && !in_array($value, $available, true))) {
                continue;
            }
            $options[] = ['value' => $value, 'label' => $label];
        }

        return $options;
    }

    /**
     * "TCEFORM.<table>.<field>" of the page, where an integrator keeps, removes or renames
     * the items of a column.
     *
     * @return array<string, mixed>
     */
    private function getColumnTsConfig(Filter $filter, int $pageId): array
    {
        $tsConfig = BackendUtility::getPagesTSconfig($pageId)['TCEFORM.'][$filter->table . '.'][$filter->name . '.'] ?? null;

        return is_array($tsConfig) ? $tsConfig : [];
    }

    /**
     * @param string[]|null $available
     * @return array<int, array{value: string, label: string}>
     */
    private function getRecordOptions(Filter $filter, string $foreignTable, string $search, ?array $available, int $limit): array
    {
        $labelFields = $this->getLabelFields($foreignTable);
        $queryBuilder = $this->recordQuery($filter, $foreignTable, $search, $available, $labelFields);
        $queryBuilder->select('*')->setMaxResults($limit);
        foreach ($labelFields as $labelField) {
            $queryBuilder->addOrderBy($labelField);
        }

        $options = [];
        foreach ($queryBuilder->executeQuery()->fetchAllAssociative() as $row) {
            $options[] = [
                'value' => (string)$row['uid'],
                'label' => BackendUtility::getRecordTitle($foreignTable, $row) ?: '#' . $row['uid'],
            ];
        }

        return $options;
    }

    /**
     * @param string[]|null $available
     */
    private function countRecordOptions(Filter $filter, string $foreignTable, string $search, ?array $available): int
    {
        $queryBuilder = $this->recordQuery($filter, $foreignTable, $search, $available, $this->getLabelFields($foreignTable));

        return (int)$queryBuilder->count('uid')->executeQuery()->fetchOne();
    }

    /**
     * @param string[]|null $available
     * @param string[] $labelFields
     */
    private function recordQuery(Filter $filter, string $foreignTable, string $search, ?array $available, array $labelFields): QueryBuilder
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($foreignTable);
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $queryBuilder->from($foreignTable);

        if ($available !== null) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->in(
                    'uid',
                    $queryBuilder->createNamedParameter(array_map(intval(...), $available), Connection::PARAM_INT_ARRAY)
                )
            );
        }
        if ($search !== '') {
            $queryBuilder->andWhere($queryBuilder->expr()->or(...$this->getSearchConstraints($queryBuilder, $labelFields, $search)));
        }

        $this->eventDispatcher->dispatch(new ModifyFilterQueryEvent($queryBuilder, $foreignTable, $filter));

        return $queryBuilder;
    }

    /**
     * Values occurring in records matching the parents, NULL when nothing narrows this
     * filter. Only where no join is possible: items, comma separated fields, self relations.
     *
     * @param array<string, Filter> $parents
     * @param array<string, mixed> $parentValues
     * @return string[]|null
     */
    private function getAvailableValues(Filter $filter, array $parents, array $parentValues, int $pageId): ?array
    {
        if ($parentValues === []) {
            return null;
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($filter->table);
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $queryBuilder
            ->from($filter->table)
            ->where(
                $queryBuilder->expr()->eq(
                    $filter->table . '.pid',
                    $queryBuilder->createNamedParameter($pageId, Connection::PARAM_INT)
                )
            );
        $this->constraintBuilder->apply($queryBuilder, $parents, $parentValues);
        $this->eventDispatcher->dispatch(new ModifyFilterQueryEvent($queryBuilder, $filter->table, $filter));

        $mmTable = $filter->getMmTable();
        if ($mmTable !== null) {
            [$recordColumn, $valueColumn] = $filter->hasOppositeMmSide()
                ? ['uid_foreign', 'uid_local']
                : ['uid_local', 'uid_foreign'];
            $queryBuilder
                ->select('mm.' . $valueColumn)
                ->distinct()
                ->join(
                    $filter->table,
                    $mmTable,
                    'mm',
                    $queryBuilder->expr()->eq('mm.' . $recordColumn, $queryBuilder->quoteIdentifier($filter->table . '.uid'))
                );
            foreach ($filter->getMmMatchFields() as $matchField => $matchValue) {
                $queryBuilder->andWhere(
                    $queryBuilder->expr()->eq('mm.' . $matchField, $queryBuilder->createNamedParameter($matchValue))
                );
            }

            return array_map(strval(...), $queryBuilder->executeQuery()->fetchFirstColumn());
        }

        $queryBuilder->select($filter->table . '.' . $filter->name)->distinct();
        $available = [];
        foreach ($queryBuilder->executeQuery()->fetchFirstColumn() as $value) {
            foreach (GeneralUtility::trimExplode(',', (string)$value, true) as $singleValue) {
                $available[$singleValue] = true;
            }
        }

        return array_keys($available);
    }

    /**
     * @param string[] $labelFields
     * @return string[]
     */
    private function getSearchConstraints(QueryBuilder $queryBuilder, array $labelFields, string $search, string $table = ''): array
    {
        $prefix = $table !== '' ? $table . '.' : '';
        $escaped = '%' . $queryBuilder->escapeLikeWildcards($search) . '%';
        $constraints = array_map(
            static fn(string $labelField): string => $queryBuilder->expr()->like(
                $prefix . $labelField,
                $queryBuilder->createNamedParameter($escaped)
            ),
            $labelFields
        );
        if (MathUtility::canBeInterpretedAsInteger($search)) {
            $constraints[] = $queryBuilder->expr()->eq($prefix . 'uid', $queryBuilder->createNamedParameter((int)$search, Connection::PARAM_INT));
        }

        return $constraints;
    }

    /**
     * @return string[]
     */
    private function getLabelFields(string $table): array
    {
        $labelCapability = $this->tcaSchemaFactory->get($table)->getCapability(TcaSchemaCapability::Label);
        $labelFields = array_values(array_filter($labelCapability->getAllLabelFieldNames()));

        return $labelFields !== [] ? $labelFields : ['uid'];
    }

    private function matches(string $label, string $search): bool
    {
        return $search === '' || mb_stripos($label, $search) !== false;
    }

    private function isReadable(string $table): bool
    {
        return $this->tcaSchemaFactory->has($table) && $this->getBackendUser()->check('tables_select', $table);
    }

    private function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }

    private function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}

<?php

declare(strict_types=1);

namespace DigitalZombies\BeFilter\Preset;

use DigitalZombies\BeFilter\Event\ModifyPresetQueryEvent;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Saved filters: the values of a bar and the filters displayed in it. Private, shared with
 * backend groups, or public; only the owner may change one.
 */
final readonly class PresetRepository
{
    public const TABLE = 'tx_befilter_preset';

    public const VISIBILITY_PRIVATE = 'private';
    public const VISIBILITY_GROUPS = 'groups';
    public const VISIBILITY_PUBLIC = 'public';

    private const VISIBILITIES = [self::VISIBILITY_PRIVATE, self::VISIBILITY_GROUPS, self::VISIBILITY_PUBLIC];

    public function __construct(
        private ConnectionPool $connectionPool,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    /**
     * Every saved filter of a table the current user may use.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findAccessible(string $table): array
    {
        $queryBuilder = $this->createQueryBuilder();
        $queryBuilder
            ->select('*')
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->eq('table_name', $queryBuilder->createNamedParameter($table)),
                $queryBuilder->expr()->eq('hidden', 0),
                $this->accessConstraint($queryBuilder),
            )
            ->orderBy('title');

        $rows = $queryBuilder->executeQuery()->fetchAllAssociative();
        $owners = $this->resolveOwners($rows);
        $groups = $this->resolveGroupTitles($rows);

        return array_map(fn(array $row): array => $this->describe($row, $owners, $groups), $rows);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $uid, string $table): ?array
    {
        if ($uid <= 0) {
            return null;
        }

        $queryBuilder = $this->createQueryBuilder();
        $row = $queryBuilder
            ->select('*')
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('table_name', $queryBuilder->createNamedParameter($table)),
                $this->accessConstraint($queryBuilder),
            )
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        return is_array($row) ? $this->describe($row, $this->resolveOwners([$row]), $this->resolveGroupTitles([$row])) : null;
    }

    /**
     * Stores a saved filter, overwriting the one with the same title of this user.
     *
     * @param array<string, mixed> $values
     * @param string[] $visibleFilters
     * @param int[] $groups
     */
    public function save(
        string $table,
        string $title,
        array $values,
        array $visibleFilters,
        string $visibility,
        array $groups,
    ): int {
        $visibility = in_array($visibility, self::VISIBILITIES, true) ? $visibility : self::VISIBILITY_PRIVATE;
        $groups = $visibility === self::VISIBILITY_GROUPS
            ? array_values(array_unique(array_filter(array_map(intval(...), $groups), static fn(int $uid): bool => $uid > 0)))
            : [];

        $now = time();
        $data = [
            'title' => $title,
            'filter_values' => (string)json_encode($values),
            'visible_filters' => (string)json_encode(array_values($visibleFilters)),
            'visibility' => $visibility,
            'be_groups' => implode(',', $groups),
            'tstamp' => $now,
        ];

        $connection = $this->connectionPool->getConnectionForTable(self::TABLE);
        $existing = $this->findOwnByTitle($table, $title);

        if ($existing > 0) {
            $connection->update(self::TABLE, $data, ['uid' => $existing]);

            return $existing;
        }

        $record = $data + [
            'pid' => 0,
            'crdate' => $now,
            'be_user' => $this->getBackendUserId(),
            'table_name' => $table,
        ];

        $scope = $this->scope();
        if ($scope !== null) {
            $record[$scope['field']] = $scope['value'];
        }

        $connection->insert(self::TABLE, $record);

        return (int)$connection->lastInsertId();
    }

    /**
     * Only the owner may remove a saved filter, administrators may remove any.
     */
    public function delete(int $uid, string $table): bool
    {
        $preset = $this->find($uid, $table);
        if ($preset === null || !$preset['editable']) {
            return false;
        }

        $this->connectionPool->getConnectionForTable(self::TABLE)->update(
            self::TABLE,
            ['deleted' => 1, 'tstamp' => time()],
            ['uid' => $uid]
        );

        return true;
    }

    /**
     * Backend groups the current user may share with: their own, administrators all.
     *
     * @return array<int, array{uid: int, title: string}>
     */
    public function findAssignableGroups(): array
    {
        $backendUser = $this->getBackendUser();
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('be_groups');
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
            ->add(GeneralUtility::makeInstance(HiddenRestriction::class));
        $queryBuilder->select('uid', 'title')->from('be_groups')->orderBy('title');

        if (!$backendUser->isAdmin()) {
            $own = array_map(intval(...), $backendUser->userGroupsUID);
            if ($own === []) {
                return [];
            }
            $queryBuilder->where(
                $queryBuilder->expr()->in('uid', $queryBuilder->createNamedParameter($own, Connection::PARAM_INT_ARRAY))
            );
        }

        return array_map(
            static fn(array $row): array => ['uid' => (int)$row['uid'], 'title' => (string)$row['title']],
            $queryBuilder->executeQuery()->fetchAllAssociative()
        );
    }

    private function findOwnByTitle(string $table, string $title): int
    {
        $queryBuilder = $this->createQueryBuilder();
        $uid = $queryBuilder
            ->select('uid')
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->eq('table_name', $queryBuilder->createNamedParameter($table)),
                $queryBuilder->expr()->eq('title', $queryBuilder->createNamedParameter($title)),
                $queryBuilder->expr()->eq('be_user', $queryBuilder->createNamedParameter($this->getBackendUserId(), Connection::PARAM_INT)),
            )
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchOne();

        return (int)$uid;
    }

    /**
     * Own saved filters, public ones, and those shared with a group of the user.
     */
    private function accessConstraint(QueryBuilder $queryBuilder): string
    {
        $constraints = [
            $queryBuilder->expr()->eq('be_user', $queryBuilder->createNamedParameter($this->getBackendUserId(), Connection::PARAM_INT)),
            $queryBuilder->expr()->eq('visibility', $queryBuilder->createNamedParameter(self::VISIBILITY_PUBLIC)),
        ];

        foreach (array_map(intval(...), $this->getBackendUser()->userGroupsUID) as $group) {
            $constraints[] = $queryBuilder->expr()->and(
                $queryBuilder->expr()->eq('visibility', $queryBuilder->createNamedParameter(self::VISIBILITY_GROUPS)),
                $queryBuilder->expr()->inSet('be_groups', $queryBuilder->quote((string)$group)),
            );
        }

        return (string)$queryBuilder->expr()->or(...$constraints);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, string>
     */
    private function resolveOwners(array $rows): array
    {
        $ids = array_values(array_unique(array_filter(array_map(static fn(array $row): int => (int)($row['be_user'] ?? 0), $rows))));
        if ($ids === []) {
            return [];
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('be_users');
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $users = $queryBuilder
            ->select('uid', 'username', 'realName')
            ->from('be_users')
            ->where($queryBuilder->expr()->in('uid', $queryBuilder->createNamedParameter($ids, Connection::PARAM_INT_ARRAY)))
            ->executeQuery()
            ->fetchAllAssociative();

        $names = [];
        foreach ($users as $user) {
            $names[(int)$user['uid']] = trim((string)($user['realName'] ?? '')) ?: (string)$user['username'];
        }

        return $names;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, string>
     */
    private function resolveGroupTitles(array $rows): array
    {
        $ids = [];
        foreach ($rows as $row) {
            foreach (GeneralUtility::intExplode(',', (string)($row['be_groups'] ?? ''), true) as $group) {
                $ids[$group] = $group;
            }
        }
        if ($ids === []) {
            return [];
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('be_groups');
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $groups = $queryBuilder
            ->select('uid', 'title')
            ->from('be_groups')
            ->where($queryBuilder->expr()->in('uid', $queryBuilder->createNamedParameter(array_values($ids), Connection::PARAM_INT_ARRAY)))
            ->executeQuery()
            ->fetchAllAssociative();

        $titles = [];
        foreach ($groups as $group) {
            $titles[(int)$group['uid']] = (string)$group['title'];
        }

        return $titles;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, string> $owners
     * @param array<int, string> $groupTitles
     * @return array<string, mixed>
     */
    private function describe(array $row, array $owners, array $groupTitles): array
    {
        $owner = (int)($row['be_user'] ?? 0);
        $groups = GeneralUtility::intExplode(',', (string)($row['be_groups'] ?? ''), true);
        $values = json_decode((string)($row['filter_values'] ?? ''), true);
        $visible = json_decode((string)($row['visible_filters'] ?? ''), true);

        return [
            'uid' => (int)($row['uid'] ?? 0),
            'title' => (string)($row['title'] ?? ''),
            'values' => is_array($values) ? $values : [],
            'visible' => is_array($visible) ? array_map(strval(...), $visible) : [],
            'visibility' => (string)($row['visibility'] ?? self::VISIBILITY_PRIVATE),
            'groups' => array_values(array_filter(array_map(static fn(int $uid): string => $groupTitles[$uid] ?? '', $groups))),
            'owner' => $owners[$owner] ?? '',
            'own' => $owner === $this->getBackendUserId(),
            'editable' => $owner === $this->getBackendUserId() || $this->getBackendUser()->isAdmin(),
            'created' => (int)($row['crdate'] ?? 0),
        ];
    }

    private function createQueryBuilder(): QueryBuilder
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        $scope = $this->scope();
        if ($scope !== null) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->eq(
                    $scope['field'],
                    $queryBuilder->createNamedParameter($scope['value'], Connection::PARAM_INT)
                )
            );
        }

        return $queryBuilder;
    }

    /**
     * The scope an application restricts records by, NULL when there is none.
     *
     * @return array{field: string, value: int}|null
     */
    private function scope(): ?array
    {
        $event = $this->eventDispatcher->dispatch(new ModifyPresetQueryEvent(self::TABLE));

        return $event->getScopeField() === null
            ? null
            : ['field' => $event->getScopeField(), 'value' => $event->getScopeValue()];
    }

    private function getBackendUserId(): int
    {
        return (int)($this->getBackendUser()->user['uid'] ?? 0);
    }

    private function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}

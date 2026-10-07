<?php

declare(strict_types=1);

namespace DigitalZombies\BeFilter\Controller;

use DigitalZombies\BeFilter\Column\ColumnProvider;
use DigitalZombies\BeFilter\Filter\Filter;
use DigitalZombies\BeFilter\Filter\FilterRegistry;
use DigitalZombies\BeFilter\Filter\FilterSettings;
use DigitalZombies\BeFilter\Filter\FilterTypeRegistry;
use DigitalZombies\BeFilter\Filter\ReachableValues;
use DigitalZombies\BeFilter\Preset\PresetRepository;
use DigitalZombies\BeFilter\Query\OptionProvider;
use DigitalZombies\BeFilter\State\FilterStateRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;

/**
 * Endpoints behind the filter bar.
 */
#[AsController]
final readonly class FilterController
{
    private const MAX_PRESET_TITLE_LENGTH = 100;

    public function __construct(
        private readonly FilterTypeRegistry $typeRegistry,
        private FilterRegistry $filterRegistry,
        private FilterStateRepository $stateRepository,
        private OptionProvider $optionProvider,
        private ColumnProvider $columnProvider,
        private PresetRepository $presetRepository,
        private TcaSchemaFactory $tcaSchemaFactory,
        private FilterSettings $settings,
        private ReachableValues $reachableValues,
    ) {}

    public function optionsAction(ServerRequestInterface $request): ResponseInterface
    {
        $parameters = $request->getQueryParams();
        $table = $this->resolveTable($parameters);
        if ($table === null) {
            return $this->error();
        }

        $filters = $this->filterRegistry->getFilters($table);
        $filter = $filters[(string)($parameters['filter'] ?? '')] ?? null;
        if ($filter === null || $this->typeRegistry->get($filter->type)?->providesOptions() !== true) {
            return $this->error();
        }

        $values = $this->filterRegistry->normalizeValues($table, $this->decode($parameters['values'] ?? ''));
        $result = $this->optionProvider->getOptions(
            $filter,
            $filters,
            $values,
            trim((string)($parameters['search'] ?? '')),
            (int)($parameters['id'] ?? 0)
        );

        return new JsonResponse([
            'success' => true,
            'options' => $result['options'],
            'total' => $result['total'],
        ]);
    }

    public function applyAction(ServerRequestInterface $request): ResponseInterface
    {
        $parameters = (array)$request->getParsedBody();
        $table = $this->resolveTable($parameters);
        if ($table === null) {
            return $this->error();
        }

        $pageId = (int)($parameters['id'] ?? 0);
        $filters = $this->filterRegistry->getFilters($table);
        $wanted = $this->decode($parameters['values'] ?? '');
        $values = $this->filterRegistry->normalizeValues($table, $wanted);

        // A saved filter brings the filters it was displayed with along.
        $requestedNames = array_map(strval(...), $this->decode($parameters['visible'] ?? ''));
        $requested = array_values(array_filter(
            $requestedNames,
            static fn(string $name): bool => isset($filters[$name])
        ));

        // What it names but this user has no filter for is left out as well.
        $missing = $this->reachableValues->missing(
            $filters,
            array_merge(array_map(strval(...), array_keys($wanted)), $requestedNames),
        );
        $visible = $requested !== [] ? $requested : $this->stateRepository->getVisibleFilters($table, $filters);

        // A value naming a record this user cannot see would filter the listing down to
        // nothing, with nothing to explain it.
        ['values' => $values, 'adjusted' => $adjusted] = $this->reachableValues->apply($filters, $values, $pageId);

        // A filter carrying a value has to be visible, otherwise it would filter unnoticed.
        $unlisted = array_values(array_diff(array_keys($values), $visible));
        $this->stateRepository->setVisibleFilters($table, array_merge($visible, $unlisted));
        $this->stateRepository->setValues($table, $values);

        return new JsonResponse([
            'success' => true,
            'adjusted' => array_values(array_unique(array_merge($adjusted, $missing))),
        ]);
    }

    public function layoutAction(ServerRequestInterface $request): ResponseInterface
    {
        $parameters = (array)$request->getParsedBody();
        $table = $this->resolveTable($parameters);
        if ($table === null) {
            return $this->error();
        }

        $filters = $this->filterRegistry->getFilters($table);
        $visible = array_values(array_filter(
            array_map(strval(...), $this->decode($parameters['visible'] ?? '')),
            static fn(string $name): bool => isset($filters[$name])
        ));
        $this->stateRepository->setVisibleFilters($table, $visible);
        $this->stateRepository->setValues(
            $table,
            array_intersect_key($this->stateRepository->getValues($table), array_flip($visible))
        );

        return new JsonResponse(['success' => true]);
    }

    public function savePresetAction(ServerRequestInterface $request): ResponseInterface
    {
        $parameters = (array)$request->getParsedBody();
        $table = $this->resolveTable($parameters);
        $title = mb_substr(trim((string)($parameters['title'] ?? '')), 0, self::MAX_PRESET_TITLE_LENGTH);
        if ($table === null || $title === '') {
            return $this->error();
        }

        $filters = $this->filterRegistry->getFilters($table);
        $values = $this->filterRegistry->normalizeValues($table, $this->decode($parameters['values'] ?? ''));
        $visible = array_values(array_filter(
            array_map(strval(...), $this->decode($parameters['visible'] ?? '')),
            static fn(string $name): bool => isset($filters[$name])
        ));

        $this->presetRepository->save(
            $table,
            $title,
            $values,
            $visible,
            (string)($parameters['visibility'] ?? PresetRepository::VISIBILITY_PRIVATE),
            $this->decode($parameters['groups'] ?? ''),
        );

        return new JsonResponse(['success' => true, 'presets' => $this->withReachableValues(
            $this->presetRepository->findAccessible($table),
            $filters,
            (int)($parameters['id'] ?? 0),
        )]);
    }

    public function deletePresetAction(ServerRequestInterface $request): ResponseInterface
    {
        $parameters = (array)$request->getParsedBody();
        $table = $this->resolveTable($parameters);
        if ($table === null) {
            return $this->error();
        }

        $filters = $this->filterRegistry->getFilters($table);
        if (!$this->presetRepository->delete((int)($parameters['preset'] ?? 0), $table)) {
            return new JsonResponse(['success' => false, 'message' => 'Not allowed'], 403);
        }

        return new JsonResponse(['success' => true, 'presets' => $this->withReachableValues(
            $this->presetRepository->findAccessible($table),
            $filters,
            (int)($parameters['id'] ?? 0),
        )]);
    }

    /**
     * Saved filters of the table, together with the groups the user may share with.
     */
    public function presetsAction(ServerRequestInterface $request): ResponseInterface
    {
        $parameters = $request->getQueryParams();
        $table = $this->resolveTable($parameters);
        if ($table === null) {
            return $this->error();
        }

        return new JsonResponse([
            'success' => true,
            'presets' => $this->withReachableValues(
                $this->presetRepository->findAccessible($table),
                $this->filterRegistry->getFilters($table),
                (int)($parameters['id'] ?? 0),
            ),
            'groups' => $this->presetRepository->findAssignableGroups(),
        ]);
    }

    public function columnsAction(ServerRequestInterface $request): ResponseInterface
    {
        $parameters = (array)$request->getParsedBody();
        $table = $this->resolveTable($parameters);
        if ($table === null) {
            return $this->error();
        }

        $this->columnProvider->saveColumns($table, array_map(strval(...), $this->decode($parameters['columns'] ?? '')));

        return new JsonResponse(['success' => true]);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    /**
     * A saved filter is read by everybody it is shared with, and it carries the records it
     * was saved with. Only the ones this user may see leave the server.
     *
     * @param array<int, array<string, mixed>> $presets
     * @param array<string, Filter> $filters
     * @return array<int, array<string, mixed>>
     */
    private function withReachableValues(array $presets, array $filters, int $pageId): array
    {
        foreach ($presets as $index => $preset) {
            $values = is_array($preset['values'] ?? null) ? $preset['values'] : [];
            $presets[$index]['values'] = $this->reachableValues->apply($filters, $values, $pageId)['values'];
        }

        return $presets;
    }

    private function resolveTable(array $parameters): ?string
    {
        $table = (string)($parameters['table'] ?? '');

        return $table !== ''
            && $this->tcaSchemaFactory->has($table)
            && $this->getBackendUser()->check('tables_select', $table)
            && $this->settings->isEnabled($table)
                ? $table
                : null;
    }

    /**
     * @return array<mixed>
     */
    private function decode(mixed $value): array
    {
        $decoded = is_string($value) ? json_decode($value, true) : null;

        return is_array($decoded) ? $decoded : [];
    }

    private function error(): ResponseInterface
    {
        return new JsonResponse(['success' => false], 400);
    }

    private function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}

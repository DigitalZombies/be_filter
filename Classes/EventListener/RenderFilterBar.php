<?php

declare(strict_types=1);

namespace DigitalZombies\BeFilter\EventListener;

use DigitalZombies\BeFilter\Column\ColumnProvider;
use DigitalZombies\BeFilter\Filter\Filter;
use DigitalZombies\BeFilter\Filter\FilterRegistry;
use DigitalZombies\BeFilter\Filter\FilterSettings;
use DigitalZombies\BeFilter\Filter\FilterTypeRegistry;
use DigitalZombies\BeFilter\Query\OptionProvider;
use DigitalZombies\BeFilter\State\FilterStateRepository;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Controller\Event\RenderAdditionalContentToRecordListEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Renders the filter bar above the listing of a single table.
 */
final readonly class RenderFilterBar
{
    public function __construct(
        private FilterRegistry $filterRegistry,
        private FilterStateRepository $stateRepository,
        private OptionProvider $optionProvider,
        private ColumnProvider $columnProvider,
        private FilterSettings $settings,
        private FilterTypeRegistry $typeRegistry,
        private TcaSchemaFactory $tcaSchemaFactory,
        private PageRenderer $pageRenderer,
    ) {}

    #[AsEventListener('be-filter/render-filter-bar')]
    public function __invoke(RenderAdditionalContentToRecordListEvent $event): void
    {
        $request = $event->getRequest();
        $table = $this->getParameter($request, 'table');
        if ($table === '' || !$this->tcaSchemaFactory->has($table) || !$this->getBackendUser()->check('tables_select', $table)) {
            return;
        }
        if (!$this->settings->isEnabled($table)) {
            return;
        }

        $pageId = (int)$this->getParameter($request, 'id');
        $filters = $this->filterRegistry->getFilters($table);
        $values = $this->filterRegistry->normalizeValues($table, $this->stateRepository->getValues($table));

        $configuration = [
            'table' => $table,
            'pageId' => $pageId,
            'filters' => array_values(array_map(
                fn(Filter $filter): array => $this->describeFilter($filter, $values),
                $filters
            )),
            'visible' => $this->stateRepository->getVisibleFilters($table, $filters),
            'values' => (object)$values,
            'columns' => $this->columnProvider->getColumns($table, $pageId),
            'autoRefresh' => $this->settings->isAutoRefresh($table),
        ];

        $this->pageRenderer->loadJavaScriptModule('@digitalzombies/be_filter/filter-bar.js');
        // Types of other extensions bring the control registering itself.
        foreach ($this->typeRegistry->getJavaScriptModules() as $module) {
            $this->pageRenderer->loadJavaScriptModule($module);
        }
        $this->pageRenderer->addCssFile('EXT:be_filter/Resources/Public/Css/be_filter.css');
        $event->addContentAbove(sprintf(
            '<be-filter-bar configuration="%s"></be-filter-bar>',
            GeneralUtility::jsonEncodeForHtmlAttribute($configuration)
        ));
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function describeFilter(Filter $filter, array $values): array
    {
        $type = $this->typeRegistry->get($filter->type);
        $description = [
            'name' => $filter->name,
            'label' => $filter->label,
            'type' => $filter->type,
            'multiple' => $filter->multiple,
            'dependsOn' => $filter->dependsOn,
        ];

        if ($type === null) {
            return $description;
        }

        $description = array_merge($description, $type->describe($filter));

        if ($type->providesOptions()) {
            $labels = $this->optionProvider->getLabels($filter, (array)($values[$filter->name] ?? []));
            $description['selected'] = array_map(
                static fn(string $value, string $label): array => ['value' => $value, 'label' => $label],
                array_keys($labels),
                array_values($labels)
            );
        }

        return $description;
    }

    private function getParameter(ServerRequestInterface $request, string $name): string
    {
        return (string)($request->getParsedBody()[$name] ?? $request->getQueryParams()[$name] ?? '');
    }

    private function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}

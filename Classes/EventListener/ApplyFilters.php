<?php

declare(strict_types=1);

namespace DigitalZombies\BeFilter\EventListener;

use DigitalZombies\BeFilter\Filter\FilterRegistry;
use DigitalZombies\BeFilter\Query\ConstraintBuilder;
use DigitalZombies\BeFilter\State\FilterStateRepository;
use TYPO3\CMS\Backend\View\Event\ModifyDatabaseQueryForRecordListingEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;

/**
 * Adds the constraints of the current user to the listing, in the single table view only:
 * filtering a table whose bar is not shown would happen unnoticed.
 */
final readonly class ApplyFilters
{
    public function __construct(
        private FilterRegistry $filterRegistry,
        private FilterStateRepository $stateRepository,
        private ConstraintBuilder $constraintBuilder,
    ) {}

    #[AsEventListener('be-filter/apply-filters')]
    public function __invoke(ModifyDatabaseQueryForRecordListingEvent $event): void
    {
        $table = $event->getTable();
        if ($event->getDatabaseRecordList()->table !== $table) {
            return;
        }

        $filters = $this->filterRegistry->getFilters($table);
        if ($filters === []) {
            return;
        }

        $values = $this->filterRegistry->normalizeValues($table, $this->stateRepository->getValues($table));
        $visible = $this->stateRepository->getVisibleFilters($table, $filters);

        $this->constraintBuilder->apply(
            $event->getQueryBuilder(),
            $filters,
            array_intersect_key($values, array_flip($visible))
        );
    }
}

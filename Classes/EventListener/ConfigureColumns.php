<?php

declare(strict_types=1);

namespace DigitalZombies\BeFilter\EventListener;

use TYPO3\CMS\Backend\View\Event\ModifyDatabaseQueryForRecordListingEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Schema\Capability\TcaSchemaCapability;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;

/**
 * Renders the columns in the order the user arranged them, which the record list stores
 * but does not apply, and hands the selection to the filter bar.
 */
final readonly class ConfigureColumns
{
    public function __construct(private TcaSchemaFactory $tcaSchemaFactory) {}

    #[AsEventListener('be-filter/configure-columns')]
    public function __invoke(ModifyDatabaseQueryForRecordListingEvent $event): void
    {
        $recordList = $event->getDatabaseRecordList();
        $table = $event->getTable();

        if ($recordList->table === $table) {
            // The filter bar carries the column configuration of the single table view.
            $recordList->displayColumnSelector = false;
        }

        $order = $recordList->setFields[$table] ?? null;
        // The counting query runs before the columns of the table are known.
        if ($event->getFields() === ['*'] || !is_array($order) || $order === [] || $recordList->fieldArray === []) {
            return;
        }

        $recordList->fieldArray = $this->applyOrder($recordList->fieldArray, $order, $table);
    }

    /**
     * @param array<int, string> $fieldArray
     * @param array<int, string> $order
     * @return array<int, string>
     */
    private function applyOrder(array $fieldArray, array $order, string $table): array
    {
        $labelField = $this->tcaSchemaFactory->get($table)->getCapability(TcaSchemaCapability::Label)->getPrimaryFieldName();
        // The label column has to stay where it is, it always follows the record icon.
        $sorted = array_values(array_intersect(
            array_filter($order, static fn(string $field): bool => $field !== $labelField),
            $fieldArray
        ));
        $positions = array_keys(array_filter(
            $fieldArray,
            static fn(string $field): bool => in_array($field, $sorted, true)
        ));

        foreach ($positions as $index => $position) {
            $fieldArray[$position] = $sorted[$index];
        }

        return $fieldArray;
    }
}

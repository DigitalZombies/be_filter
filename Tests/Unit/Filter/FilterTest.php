<?php

declare(strict_types=1);

namespace DigitalZombies\BeFilter\Tests\Unit\Filter;

use DigitalZombies\BeFilter\Filter\Filter;
use PHPUnit\Framework\TestCase;

/**
 * The filter itself only describes how the values of its field are stored.
 */
final class FilterTest extends TestCase
{
    public function testTheForeignTableOfAGroupIsOnlyUsedWhenItIsUnambiguous(): void
    {
        $single = new Filter(table: 'tx_test', name: 'rel', type: 'select', label: 'R', config: ['type' => 'group', 'allowed' => 'tx_one']);
        $several = new Filter(table: 'tx_test', name: 'rel', type: 'select', label: 'R', config: ['type' => 'group', 'allowed' => 'tx_one,tx_two']);

        self::assertSame('tx_one', $single->getForeignTable());
        self::assertNull($several->getForeignTable());
    }

    public function testDividersAndEmptyValuesAreNoItems(): void
    {
        $filter = new Filter(
            table: 'tx_test',
            name: 'fuel',
            type: 'select',
            label: 'Fuel',
            config: ['items' => [
                ['label' => 'Diesel', 'value' => 'diesel'],
                ['label' => 'Group', 'value' => '--div--'],
                ['label' => 'Empty', 'value' => ''],
            ]]
        );

        self::assertCount(1, $filter->getItems());
    }

    public function testAnInvertedCheckboxIsRecognised(): void
    {
        $plain = new Filter(table: 'tx_test', name: 'available', type: 'checkbox', label: 'A', config: ['items' => [['label' => '']]]);
        $inverted = new Filter(table: 'tx_test', name: 'hidden', type: 'checkbox', label: 'V', config: ['items' => [['label' => '', 'invertStateDisplay' => true]]]);

        self::assertFalse($plain->isInverted());
        self::assertTrue($inverted->isInverted());
    }

    public function testTheSwitchRenderingComesFromTheTypeOptions(): void
    {
        $dropdown = new Filter(table: 'tx_test', name: 'available', type: 'checkbox', label: 'A');
        $switch = new Filter(table: 'tx_test', name: 'available', type: 'checkbox', label: 'A', typeOptions: ['checkbox' => true]);

        self::assertFalse($dropdown->asCheckbox());
        self::assertTrue($switch->asCheckbox());
    }

    public function testTheDateStorageFormatFollowsTheColumn(): void
    {
        $timestamp = new Filter(table: 'tx_test', name: 'crdate', type: 'date', label: 'C');
        $native = new Filter(table: 'tx_test', name: 'day', type: 'date', label: 'D', config: ['dbType' => 'date']);

        self::assertNull($timestamp->getDateStorageFormat());
        self::assertSame('Y-m-d', $native->getDateStorageFormat());
    }

    public function testTheMmTableAndWhatNarrowsItAreReadFromTheColumn(): void
    {
        $plain = new Filter(table: 'tx_test', name: 'rel', type: 'select', label: 'R', config: ['type' => 'select', 'foreign_table' => 'tx_other']);
        $category = new Filter(table: 'tx_test', name: 'cat', type: 'select', label: 'C', config: [
            'type' => 'category',
            'MM' => 'sys_category_record_mm',
            'MM_opposite_field' => 'items',
            'MM_match_fields' => ['tablenames' => 'tx_test', 'fieldname' => 'cat'],
        ]);

        self::assertNull($plain->getMmTable());
        self::assertFalse($plain->hasOppositeMmSide());
        self::assertSame([], $plain->getMmMatchFields());

        self::assertSame('sys_category_record_mm', $category->getMmTable());
        self::assertTrue($category->hasOppositeMmSide(), 'A category keeps the record in uid_foreign');
        self::assertSame(['tablenames' => 'tx_test', 'fieldname' => 'cat'], $category->getMmMatchFields());
    }

    public function testADateTimeColumnIsStoredWithItsTime(): void
    {
        $datetime = new Filter(table: 'tx_test', name: 'at', type: 'date', label: 'A', config: ['dbType' => 'datetime']);

        self::assertSame('Y-m-d H:i:s', $datetime->getDateStorageFormat());
    }

    /**
     * A relation to a foreign table is stored as a comma separated list, whatever its
     * maxitems says, unless an MM table takes the values. Static items are not a list
     * until the column allows several of them.
     */
    public function testHowTheValuesOfTheFieldAreStored(): void
    {
        $relation = new Filter(table: 'tx_test', name: 'rel', type: 'select', label: 'R', config: ['type' => 'select', 'foreign_table' => 'tx_other', 'maxitems' => 1]);
        $mm = new Filter(table: 'tx_test', name: 'rel', type: 'select', label: 'R', config: ['type' => 'select', 'foreign_table' => 'tx_other', 'MM' => 'tx_mm']);
        $singleItem = new Filter(table: 'tx_test', name: 'fuel', type: 'select', label: 'F', config: ['type' => 'select', 'items' => [['label' => 'A', 'value' => 'a']]]);
        $severalItems = new Filter(table: 'tx_test', name: 'fuel', type: 'select', label: 'F', config: ['type' => 'select', 'items' => [['label' => 'A', 'value' => 'a']], 'maxitems' => 9]);

        self::assertTrue($relation->isMultiValue(), 'A relation without MM is a list');
        self::assertFalse($mm->isMultiValue(), 'An MM relation keeps its values in the MM table');
        self::assertFalse($singleItem->isMultiValue());
        self::assertTrue($severalItems->isMultiValue());
    }

    /**
     * The core defaults "maxitems" for categories only, so the render type has to say
     * whether static items are stored as a list.
     */
    public function testStaticItemsSelectedSeveralAtOnceAreAListWithoutMaxitems(): void
    {
        $items = [['label' => 'A', 'value' => 'a'], ['label' => 'B', 'value' => 'b']];
        $filter = static fn(array $config): Filter => new Filter(table: 'tx_test', name: 'fuel', type: 'select', label: 'F', config: ['type' => 'select', 'items' => $items] + $config);

        self::assertTrue($filter(['renderType' => 'selectCheckBox'])->isMultiValue());
        self::assertTrue($filter(['renderType' => 'selectMultipleSideBySide'])->isMultiValue());
        self::assertTrue($filter(['renderType' => 'selectSingleBox'])->isMultiValue());
        self::assertFalse($filter(['renderType' => 'selectSingle'])->isMultiValue());
        self::assertFalse($filter(['renderType' => 'selectCheckBox', 'maxitems' => 1])->isMultiValue(), 'An explicit maxitems decides');
        self::assertFalse(
            (new Filter(table: 'tx_test', name: 'kind', type: 'select', label: 'K', config: ['type' => 'radio', 'items' => $items]))->isMultiValue()
        );
    }
}

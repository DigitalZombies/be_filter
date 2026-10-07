<?php

declare(strict_types=1);

namespace DigitalZombies\BeFilter\Tests\Unit\Filter\Type;

use DigitalZombies\BeFilter\Filter\Filter;
use DigitalZombies\BeFilter\Filter\Type\SelectFilterType;
use DigitalZombies\BeFilter\Tests\Unit\QueryBuilderTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\QueryRestrictionContainerInterface;

final class SelectFilterTypeTest extends TestCase
{
    use QueryBuilderTrait;

    private ConnectionPool&MockObject $connectionPool;

    private SelectFilterType $subject;

    protected function setUp(): void
    {
        $this->connectionPool = $this->createMock(ConnectionPool::class);
        $this->subject = new SelectFilterType($this->connectionPool);
    }

    private function filter(bool $multiple = false): Filter
    {
        return new Filter(table: 'tx_test', name: 'field', type: 'select', label: 'Field', multiple: $multiple);
    }

    public function testItTakesColumnsThatHaveSomethingToOffer(): void
    {
        self::assertTrue($this->subject->matches(['type' => 'select', 'foreign_table' => 'tx_other'], []));
        self::assertTrue($this->subject->matches(['type' => 'select', 'items' => [['label' => 'A', 'value' => 'a']]], []));
        self::assertFalse($this->subject->matches(['type' => 'select'], []), 'Without values there is nothing to offer');
        self::assertFalse($this->subject->matches(['type' => 'input'], []));
    }

    public function testASingleSelectKeepsTheFirstValueOnly(): void
    {
        self::assertSame(['5'], $this->subject->normalizeValue(['5', '6'], $this->filter()));
        self::assertSame(['5', '6'], $this->subject->normalizeValue(['5', '6'], $this->filter(multiple: true)));
    }

    public function testEmptyAndAmbiguousValuesAreDropped(): void
    {
        $filter = $this->filter(multiple: true);

        self::assertSame(['5'], $this->subject->normalizeValue(['5', '', '  ', '7,8'], $filter), 'A comma would be ambiguous');
        self::assertNull($this->subject->normalizeValue([], $filter));
        self::assertNull($this->subject->normalizeValue([''], $filter));
    }

    public function testItOffersOptions(): void
    {
        self::assertTrue($this->subject->providesOptions());
        self::assertSame('select', $this->subject->getIdentifier());
    }

    public function testASingleValuedColumnIsMatchedWithIn(): void
    {
        $filter = new Filter(
            table: 'tx_test',
            name: 'fuel',
            type: 'select',
            label: 'F',
            config: ['type' => 'select', 'items' => [['label' => 'Diesel', 'value' => 'diesel']]],
        );

        self::assertSame(
            'tx_test.fuel IN (diesel)',
            (string)$this->subject->buildConstraint($this->queryBuilder(), $filter, ['diesel']),
        );
        self::assertNull($this->subject->buildConstraint($this->queryBuilder(), $filter, []));
    }

    public function testACommaSeparatedRelationIsMatchedValueByValue(): void
    {
        $filter = new Filter(
            table: 'tx_test',
            name: 'rel',
            type: 'select',
            label: 'R',
            config: ['type' => 'select', 'foreign_table' => 'tx_other'],
        );

        $constraint = (string)$this->subject->buildConstraint($this->queryBuilder(), $filter, ['5', '6']);

        self::assertStringContainsString("FIND_IN_SET('5', tx_test.rel)", $constraint);
        self::assertStringContainsString("FIND_IN_SET('6', tx_test.rel)", $constraint);
        self::assertStringContainsString('OR', $constraint, 'Either value is a match');
    }

    public function testAnMmRelationIsMatchedWithASubQueryOverTheMmTable(): void
    {
        $subQuery = $this->queryBuilder();
        $subQuery->method('select')->willReturnSelf();
        $subQuery->method('from')->willReturnSelf();
        $subQuery->method('where')->willReturnSelf();
        $subQuery->method('andWhere')->willReturnSelf();
        $subQuery->method('getRestrictions')->willReturn($this->createMock(QueryRestrictionContainerInterface::class));
        $subQuery->method('getSQL')->willReturn('SELECT uid_local FROM tx_mm');
        $this->connectionPool->method('getQueryBuilderForTable')->willReturn($subQuery);

        $filter = new Filter(
            table: 'tx_test',
            name: 'features',
            type: 'select',
            label: 'F',
            config: ['type' => 'select', 'foreign_table' => 'tx_other', 'MM' => 'tx_mm'],
        );

        self::assertSame(
            'tx_test.uid IN (SELECT uid_local FROM tx_mm)',
            (string)$this->subject->buildConstraint($this->queryBuilder(), $filter, ['5']),
        );
    }

    public function testItShipsItsOwnControl(): void
    {
        self::assertSame('@digitalzombies/be_filter/select-filter.js', $this->subject->getJavaScriptModule());
    }
}

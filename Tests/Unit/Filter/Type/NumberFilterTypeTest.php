<?php

declare(strict_types=1);

namespace DigitalZombies\BeFilter\Tests\Unit\Filter\Type;

use DigitalZombies\BeFilter\Filter\Filter;
use DigitalZombies\BeFilter\Filter\Type\NumberFilterType;
use DigitalZombies\BeFilter\Tests\Unit\QueryBuilderTrait;
use PHPUnit\Framework\TestCase;

final class NumberFilterTypeTest extends TestCase
{
    use QueryBuilderTrait;

    private NumberFilterType $subject;

    protected function setUp(): void
    {
        $this->subject = new NumberFilterType();
    }

    public function testNumberColumnsAndTheirLegacyFormAreTaken(): void
    {
        self::assertTrue($this->subject->matches(['type' => 'number'], []));
        self::assertTrue($this->subject->matches(['type' => 'input', 'eval' => 'trim,int'], []));
        self::assertTrue($this->subject->matches(['type' => 'input', 'eval' => 'double2'], []));
        self::assertFalse($this->subject->matches(['type' => 'input', 'eval' => 'trim'], []));
        self::assertFalse($this->subject->matches(['type' => 'text'], []));
    }

    public function testBoundsAndStepComeFromTheColumn(): void
    {
        $options = $this->subject->buildTypeOptions([], ['type' => 'number', 'range' => ['lower' => 1, 'upper' => 9]]);

        self::assertSame(1, $options['min']);
        self::assertSame(9, $options['max']);
        self::assertSame(1, $options['step']);
        self::assertSame(0, $options['decimals']);
        self::assertSame('input', $options['render']);
        self::assertSame('range', $options['mode']);
    }

    public function testTheFilterMayOverrideWhatTheColumnSays(): void
    {
        $options = $this->subject->buildTypeOptions(
            ['min' => 100, 'max' => 300000, 'step' => 500],
            ['type' => 'number', 'range' => ['lower' => 0, 'upper' => 10]]
        );

        self::assertSame(100, $options['min']);
        self::assertSame(300000, $options['max']);
        self::assertSame(500, $options['step']);
    }

    public function testADecimalColumnGetsAFractionalStep(): void
    {
        $options = $this->subject->buildTypeOptions([], ['type' => 'number', 'format' => 'decimal']);

        self::assertSame(2, $options['decimals']);
        self::assertSame(0.01, $options['step']);

        $configured = $this->subject->buildTypeOptions(['decimals' => 1], ['type' => 'number', 'format' => 'decimal']);
        self::assertSame(0.1, $configured['step']);
    }

    public function testTheSliderIsAskedForInTheDefinition(): void
    {
        self::assertSame('slider', $this->subject->buildTypeOptions(['render' => 'slider'], [])['render']);
        self::assertSame('max', $this->subject->buildTypeOptions(['render' => 'slider', 'mode' => 'max'], [])['mode']);
        self::assertSame('exact', $this->subject->buildTypeOptions(['mode' => 'exact'], [])['mode']);
        self::assertSame('range', $this->subject->buildTypeOptions(['mode' => 'nonsense'], [])['mode'], 'An unknown mode is a range');
    }

    public function testTheControlReadsItsBoundsUnderNumber(): void
    {
        $filter = new Filter(table: 'tx_test', name: 'price', type: 'number', label: 'Price', typeOptions: ['min' => 0, 'step' => 500]);

        self::assertSame(['number' => ['min' => 0, 'step' => 500]], $this->subject->describe($filter));
    }

    public function testTheCaptionsOfTheRangeTravelWithTheBounds(): void
    {
        $options = $this->subject->buildTypeOptions(['labels' => ['from' => 'At least', 'to' => 'At most']], []);

        self::assertSame(['from' => 'At least', 'to' => 'At most'], $options['labels']);
        self::assertSame([], $this->subject->buildTypeOptions([], [])['labels'], 'Without configuration the translation stands');
    }

    public function testBothNotationsAreAcceptedAndARangeMayStayOpen(): void
    {
        $filter = new Filter(table: 'tx_test', name: 'price', type: 'number', label: 'Price', typeOptions: ['decimals' => 1]);

        self::assertSame(['from' => '10', 'to' => '20.5'], $this->subject->normalizeValue(['from' => '10', 'to' => '20.5'], $filter));
        self::assertSame(['from' => '1.5'], $this->subject->normalizeValue(['from' => '1,5'], $filter), 'A comma is a decimal separator');
        self::assertSame(['to' => '99'], $this->subject->normalizeValue(['from' => '', 'to' => '99'], $filter));
        self::assertNull($this->subject->normalizeValue(['from' => 'abc'], $filter));
        self::assertNull($this->subject->normalizeValue([], $filter));
    }

    public function testBothEndsBecomeAComparisonAndAnOpenRangeOnlyOne(): void
    {
        $filter = new Filter(table: 'tx_test', name: 'price', type: 'number', label: 'P');

        $range = (string)$this->subject->buildConstraint($this->queryBuilder(), $filter, ['from' => '1000', 'to' => '5000']);
        self::assertStringContainsString('tx_test.price >= 1000', $range);
        self::assertStringContainsString('tx_test.price <= 5000', $range);

        $open = (string)$this->subject->buildConstraint($this->queryBuilder(), $filter, ['from' => '1000']);
        self::assertSame('tx_test.price >= 1000', $open, 'A range open at the top constrains one end');

        self::assertNull($this->subject->buildConstraint($this->queryBuilder(), $filter, []));
    }

    public function testADecimalIsComparedAsADecimal(): void
    {
        $filter = new Filter(table: 'tx_test', name: 'consumption', type: 'number', label: 'C', typeOptions: ['decimals' => 1]);

        $constraint = (string)$this->subject->buildConstraint($this->queryBuilder(), $filter, ['from' => '5.5']);

        self::assertStringContainsString('5.5', $constraint);
    }

    public function testItShipsItsOwnControl(): void
    {
        self::assertSame('@digitalzombies/be_filter/number-filter.js', $this->subject->getJavaScriptModule());
    }
}

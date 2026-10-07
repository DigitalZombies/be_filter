<?php

declare(strict_types=1);

namespace DigitalZombies\BeFilter\Tests\Unit\Filter\Type;

use DigitalZombies\BeFilter\Filter\DatePreset;
use DigitalZombies\BeFilter\Filter\DatePresetRegistry;
use DigitalZombies\BeFilter\Filter\Filter;
use DigitalZombies\BeFilter\Filter\Type\DateFilterType;
use DigitalZombies\BeFilter\Tests\Unit\QueryBuilderTrait;
use PHPUnit\Framework\TestCase;

final class DateFilterTypeTest extends TestCase
{
    use QueryBuilderTrait;

    private DateFilterType $subject;

    protected function setUp(): void
    {
        unset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['be_filter'], $GLOBALS['LANG']);
        $this->subject = new DateFilterType(new DatePresetRegistry());
    }

    private function filter(array $presets = [], array $labels = []): Filter
    {
        return new Filter(
            table: 'tx_test',
            name: 'crdate',
            type: 'date',
            label: 'Created',
            typeOptions: ['presets' => $presets, 'labels' => $labels],
        );
    }

    public function testOnlyDateLikeColumnsAreTaken(): void
    {
        self::assertTrue($this->subject->matches(['type' => 'datetime'], []));
        self::assertTrue($this->subject->matches(['type' => 'datetime', 'format' => 'date'], []));
        self::assertFalse($this->subject->matches(['type' => 'datetime', 'format' => 'time'], []), 'A time of day is not a date range');
        self::assertFalse($this->subject->matches(['type' => 'input'], []));
    }

    public function testTheQuickRangesBecomeTypeOptions(): void
    {
        $options = $this->subject->buildTypeOptions([], []);

        self::assertArrayHasKey('presets', $options);
        self::assertContainsOnlyInstancesOf(DatePreset::class, $options['presets']);
        self::assertSame(['week', 'month', 'lastMonth', 'thisMonth', 'lastYear', 'thisYear'], array_keys($options['presets']));
    }

    public function testTheControlIsToldTheKeysAndLabels(): void
    {
        $filter = $this->filter(['week' => new DatePreset('week', 'Last week', '-1 week', 'now')]);

        self::assertSame(
            ['presets' => [['key' => 'week', 'label' => 'Last week']], 'labels' => []],
            $this->subject->describe($filter)
        );
    }

    public function testTheControlIsToldTheCaptionsOfTheRange(): void
    {
        $filter = $this->filter([], ['from' => 'Registered from', 'to' => 'Registered until']);

        self::assertSame(
            ['from' => 'Registered from', 'to' => 'Registered until'],
            $this->subject->describe($filter)['labels']
        );
    }

    public function testCaptionsResolvedByTheRegistryBecomeTypeOptions(): void
    {
        $options = $this->subject->buildTypeOptions(['labels' => ['from' => 'Built from', 'to' => '']], []);

        self::assertSame(['from' => 'Built from'], $options['labels'], 'An empty caption keeps the translation');
    }

    public function testOnlyOfferedPresetsAndIsoDatesAreAccepted(): void
    {
        $filter = $this->filter(['week' => new DatePreset('week', 'Last week', '-1 week', 'now')]);

        self::assertSame(['preset' => 'week'], $this->subject->normalizeValue(['preset' => 'week'], $filter));
        self::assertNull($this->subject->normalizeValue(['preset' => 'unknown'], $filter));
        self::assertSame(['from' => '2026-09-13'], $this->subject->normalizeValue(['from' => '2026-09-13'], $filter));
        self::assertSame(
            ['from' => '2026-01-01', 'to' => '2026-03-31'],
            $this->subject->normalizeValue(['from' => '2026-01-01', 'to' => '2026-03-31'], $filter)
        );
        self::assertNull($this->subject->normalizeValue(['from' => '13.09.2026'], $filter), 'Only ISO dates are accepted');
        self::assertNull($this->subject->normalizeValue('week', $filter));
    }

    public function testATimestampRangeLeavesOutRecordsWithoutADate(): void
    {
        $constraint = (string)$this->subject->buildConstraint(
            $this->queryBuilder(),
            $this->filter(),
            ['from' => '2026-01-01', 'to' => '2026-01-31'],
        );

        self::assertStringContainsString('tx_test.crdate <> 0', $constraint, 'An empty date must not match an open range');
        self::assertStringContainsString('tx_test.crdate >=', $constraint);
        self::assertStringContainsString('tx_test.crdate <=', $constraint);
    }

    public function testATimestampBefore1970IsStillInRange(): void
    {
        $constraint = (string)$this->subject->buildConstraint(
            $this->queryBuilder(),
            $this->filter(),
            ['to' => '1965-06-30'],
        );

        self::assertStringContainsString('tx_test.crdate <> 0', $constraint);
        self::assertStringNotContainsString('tx_test.crdate > 0', $constraint, 'A negative timestamp is a date as well');
        self::assertMatchesRegularExpression('/tx_test\\.crdate <= -\\d+/', $constraint);
    }

    public function testANativeDateColumnIsComparedAsAFormattedDate(): void
    {
        $filter = new Filter(
            table: 'tx_test',
            name: 'day',
            type: 'date',
            label: 'D',
            config: ['dbType' => 'date'],
            typeOptions: ['presets' => [], 'labels' => []],
        );

        $constraint = (string)$this->subject->buildConstraint($this->queryBuilder(), $filter, ['from' => '2026-01-01']);

        self::assertStringContainsString('2026-01-01', $constraint);
        self::assertStringNotContainsString('<> 0', $constraint, 'A date column has no timestamp to guard');
    }

    public function testAValueThatResolvesToNoRangeConstrainsNothing(): void
    {
        self::assertNull($this->subject->buildConstraint($this->queryBuilder(), $this->filter(), []));
    }

    public function testItShipsItsOwnControl(): void
    {
        self::assertSame('@digitalzombies/be_filter/date-filter.js', $this->subject->getJavaScriptModule());
    }
}

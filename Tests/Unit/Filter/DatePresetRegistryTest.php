<?php

declare(strict_types=1);

namespace DigitalZombies\BeFilter\Tests\Unit\Filter;

use DigitalZombies\BeFilter\Filter\DatePresetRegistry;
use PHPUnit\Framework\TestCase;

final class DatePresetRegistryTest extends TestCase
{
    private DatePresetRegistry $subject;

    protected function setUp(): void
    {
        unset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['be_filter'], $GLOBALS['LANG']);
        $this->subject = new DatePresetRegistry();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['be_filter']);
    }

    public function testWithoutConfigurationTheDefaultsAreOffered(): void
    {
        $presets = $this->subject->getPresets([]);

        self::assertSame(['week', 'month', 'lastMonth', 'thisMonth', 'lastYear', 'thisYear'], array_keys($presets));
        self::assertSame('-1 week', $presets['week']->from);
        self::assertSame('now', $presets['week']->to);
    }

    public function testConfiguredPresetsReplaceTheDefaults(): void
    {
        $presets = $this->subject->getPresets(['presets' => ['thisMonth' => true]]);

        self::assertSame(['thisMonth'], array_keys($presets));
        self::assertSame('first day of this month', $presets['thisMonth']->from);
    }

    public function testADefaultKeepsItsRangeWhenOnlyTheLabelIsGiven(): void
    {
        $presets = $this->subject->getPresets(['presets' => ['week' => ['label' => 'Last seven days']]]);

        self::assertSame('Last seven days', $presets['week']->label);
        self::assertSame('-1 week', $presets['week']->from);
    }

    public function testOwnPresetsAreOfferedInTheOrderGiven(): void
    {
        $presets = $this->subject->getPresets([
            'presets' => [
                'quarter' => ['label' => 'Quarter', 'from' => '-3 months'],
                'overdue' => ['label' => 'Overdue', 'to' => 'yesterday'],
            ],
        ]);

        self::assertSame(['quarter', 'overdue'], array_keys($presets));
        self::assertNull($presets['quarter']->to, 'An end that is not given stays open');
        self::assertNull($presets['overdue']->from);
    }

    public function testValueIsTheShortFormOfARangeUntilNow(): void
    {
        $presets = $this->subject->getPresets(['presets' => ['recent' => ['label' => 'Recent', 'value' => '-7 days']]]);

        self::assertSame('-7 days', $presets['recent']->from);
        self::assertSame('now', $presets['recent']->to);
    }

    public function testFalseSkipsAnEntryAndAnEntryWithoutARangeIsDropped(): void
    {
        self::assertSame([], $this->subject->getPresets(['presets' => ['week' => false]]));
        self::assertSame([], $this->subject->getPresets(['presets' => ['nonsense' => ['label' => 'No range']]]));
    }

    public function testTheInstallationCanReplaceTheDefaults(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['be_filter']['datePresets'] = [
            'week' => ['label' => 'Seven days', 'from' => '-7 days'],
            'lastYear' => false,
        ];

        $presets = $this->subject->getPresets([]);

        self::assertSame('Seven days', $presets['week']->label);
        self::assertSame('-7 days', $presets['week']->from);
        self::assertArrayNotHasKey('lastYear', $presets);
        self::assertArrayHasKey('thisYear', $presets, 'Untouched defaults stay');
    }
}

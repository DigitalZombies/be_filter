<?php

declare(strict_types=1);

namespace DigitalZombies\BeFilter\Tests\Unit\Filter;

use DigitalZombies\BeFilter\Filter\FilterSettings;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;

final class FilterSettingsTest extends TestCase
{
    private const TABLE = 'tx_test_table';

    private FilterSettings $subject;

    protected function setUp(): void
    {
        unset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['be_filter'], $GLOBALS['TCA'][self::TABLE], $GLOBALS['BE_USER']);
        $this->subject = new FilterSettings();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['be_filter'], $GLOBALS['TCA'][self::TABLE], $GLOBALS['BE_USER']);
    }

    /**
     * @param array<string, mixed> $tsConfig
     */
    private function userWithTsConfig(array $tsConfig): BackendUserAuthentication
    {
        $backendUser = $this->createMock(BackendUserAuthentication::class);
        $backendUser->method('getTSConfig')->willReturn(['mod.' => ['be_filter.' => $tsConfig]]);

        return $backendUser;
    }

    public function testWithoutTsConfigEverybodyMayFilter(): void
    {
        self::assertTrue($this->subject->isEnabled(self::TABLE), 'Without a backend user either');

        $GLOBALS['BE_USER'] = $this->userWithTsConfig([]);
        self::assertTrue($this->subject->isEnabled(self::TABLE));
    }

    public function testAUserMayBeDeniedFiltersAltogether(): void
    {
        $GLOBALS['BE_USER'] = $this->userWithTsConfig(['disabled' => '1']);

        self::assertFalse($this->subject->isEnabled(self::TABLE));
        self::assertFalse($this->subject->isEnabled('tx_anything'));
    }

    public function testAUserMayBeDeniedFiltersForOneTableOnly(): void
    {
        $GLOBALS['BE_USER'] = $this->userWithTsConfig(['tables.' => [self::TABLE . '.' => ['disabled' => '1']]]);

        self::assertFalse($this->subject->isEnabled(self::TABLE));
        self::assertTrue($this->subject->isEnabled('tx_other_table'), 'Every other table stays filterable');
    }

    public function testWithoutConfigurationTheDefaultsApply(): void
    {
        self::assertFalse($this->subject->isAutoRefresh(self::TABLE));
        self::assertSame(50, $this->subject->getOptionLimit(self::TABLE));
    }

    public function testTheInstallationSetsTheDefault(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['be_filter'] = ['autoRefresh' => true, 'optionLimit' => 20];

        self::assertTrue($this->subject->isAutoRefresh(self::TABLE));
        self::assertSame(20, $this->subject->getOptionLimit(self::TABLE));
    }

    public function testATableDecidesForItself(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['be_filter'] = ['autoRefresh' => true, 'optionLimit' => 20];
        $GLOBALS['TCA'][self::TABLE]['ctrl']['be_filter'] = ['autoRefresh' => false, 'optionLimit' => 200];

        self::assertFalse($this->subject->isAutoRefresh(self::TABLE), 'The table overrides the installation');
        self::assertSame(200, $this->subject->getOptionLimit(self::TABLE));
    }

    public function testWithoutConfigurationTheRangeKeepsItsTranslation(): void
    {
        self::assertSame([], $this->subject->getRangeLabels(self::TABLE));
    }

    public function testATableAddsToTheCaptionsOfTheInstallation(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['be_filter'] = ['labels' => ['from' => 'Since', 'to' => 'Until']];
        $GLOBALS['TCA'][self::TABLE]['ctrl']['be_filter'] = ['labels' => ['from' => 'Registered from', 'to' => '']];

        self::assertSame(
            ['from' => 'Registered from', 'to' => 'Until'],
            $this->subject->getRangeLabels(self::TABLE),
            'The table overrides one end, the installation keeps the other'
        );
    }

    public function testAnEmptyLimitFallsBackToTheDefault(): void
    {
        $GLOBALS['TCA'][self::TABLE]['ctrl']['be_filter'] = ['optionLimit' => 0];

        self::assertSame(50, $this->subject->getOptionLimit(self::TABLE));
    }
}

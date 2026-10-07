<?php

declare(strict_types=1);

namespace DigitalZombies\BeFilter\Tests\Unit\State;

use DigitalZombies\BeFilter\Filter\Filter;
use DigitalZombies\BeFilter\State\FilterStateRepository;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;

/**
 * What a user has set up per table, kept in their module data.
 */
final class FilterStateRepositoryTest extends TestCase
{
    private const TABLE = 'tx_test_table';

    /**
     * @var array<string, mixed>
     */
    private array $moduleData = [];

    private FilterStateRepository $subject;

    protected function setUp(): void
    {
        $this->moduleData = [];

        $backendUser = $this->createMock(BackendUserAuthentication::class);
        $backendUser->method('getModuleData')->willReturnCallback(
            fn(string $key): mixed => $this->moduleData[$key] ?? null
        );
        $backendUser->method('pushModuleData')->willReturnCallback(
            function (string $key, mixed $data): void {
                $this->moduleData[$key] = $data;
            }
        );

        $GLOBALS['BE_USER'] = $backendUser;
        $this->subject = new FilterStateRepository();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['BE_USER']);
    }

    /**
     * @return array<string, Filter>
     */
    private function filters(): array
    {
        return [
            'manufacturer' => new Filter(table: self::TABLE, name: 'manufacturer', type: 'select', label: 'M', default: true),
            'colour' => new Filter(table: self::TABLE, name: 'colour', type: 'select', label: 'C'),
        ];
    }

    public function testValuesAndTheDisplayedFiltersSurviveEachOther(): void
    {
        $this->subject->setValues(self::TABLE, ['manufacturer' => ['3']]);
        $this->subject->setVisibleFilters(self::TABLE, ['manufacturer', 'colour']);

        self::assertSame(['manufacturer' => ['3']], $this->subject->getValues(self::TABLE));
        self::assertSame(['manufacturer', 'colour'], $this->subject->getVisibleFilters(self::TABLE, $this->filters()));
    }

    public function testUntilTheUserArrangesTheBarTheDefaultsAreDisplayed(): void
    {
        self::assertSame(['manufacturer'], $this->subject->getVisibleFilters(self::TABLE, $this->filters()));
    }

    public function testAFilterThatIsGoneIsNotDisplayedAnyMore(): void
    {
        $this->subject->setVisibleFilters(self::TABLE, ['manufacturer', 'removed']);

        self::assertSame(['manufacturer'], $this->subject->getVisibleFilters(self::TABLE, $this->filters()));
    }
}

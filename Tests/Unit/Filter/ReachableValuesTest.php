<?php

declare(strict_types=1);

namespace DigitalZombies\BeFilter\Tests\Unit\Filter;

use DigitalZombies\BeFilter\Filter\Filter;
use DigitalZombies\BeFilter\Filter\FilterTypeRegistry;
use DigitalZombies\BeFilter\Filter\ReachableValues;
use DigitalZombies\BeFilter\Filter\Type\AbstractFilterType;
use DigitalZombies\BeFilter\Query\ReachableValueLookup;
use Doctrine\DBAL\Query\Expression\CompositeExpression;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

/**
 * A saved filter carries the records it was saved with. Opened by somebody who may not see
 * them, the values that no longer resolve are left out instead of emptying the listing.
 */
final class ReachableValuesTest extends TestCase
{
    /**
     * @param string[] $reachable what the user may see, whatever was asked for
     */
    private function subject(array $reachable): ReachableValues
    {
        $lookup = new class ($reachable) implements ReachableValueLookup {
            /**
             * @param string[] $reachable
             */
            public function __construct(private readonly array $reachable) {}

            public function getReachableValues(Filter $filter, array $values, int $pageId = 0): array
            {
                return array_values(array_intersect($values, $this->reachable));
            }
        };

        return new ReachableValues($this->registry(), $lookup);
    }

    private function registry(): FilterTypeRegistry
    {
        $offersOptions = $this->type('select', true);
        $plain = $this->type('checkbox', false);

        return new FilterTypeRegistry([$offersOptions, $plain]);
    }

    private function type(string $identifier, bool $providesOptions): AbstractFilterType
    {
        return new class ($identifier, $providesOptions) extends AbstractFilterType {
            public function __construct(
                private readonly string $identifier,
                private readonly bool $providesOptions,
            ) {}

            public function getIdentifier(): string
            {
                return $this->identifier;
            }

            public function matches(array $config, array $definition): bool
            {
                return false;
            }

            public function providesOptions(): bool
            {
                return $this->providesOptions;
            }

            public function normalizeValue(mixed $value, Filter $filter): mixed
            {
                return $value;
            }

            public function buildConstraint(
                QueryBuilder $queryBuilder,
                Filter $filter,
                mixed $value
            ): string|CompositeExpression|null {
                return null;
            }
        };
    }

    /**
     * @return array<string, Filter>
     */
    private function filters(): array
    {
        return [
            'manufacturer' => new Filter(table: 'tx_test', name: 'manufacturer', type: 'select', label: 'Manufacturer'),
            'available' => new Filter(table: 'tx_test', name: 'available', type: 'checkbox', label: 'Available'),
        ];
    }

    public function testValuesEverybodyCanSeeAreLeftAlone(): void
    {
        $result = $this->subject(['3', '4'])->apply($this->filters(), ['manufacturer' => ['3', '4']]);

        self::assertSame(['manufacturer' => ['3', '4']], $result['values']);
        self::assertSame([], $result['adjusted'], 'Nothing to tell the user about');
    }

    public function testAValueOutOfReachIsLeftOutAndTheFilterIsNamed(): void
    {
        $result = $this->subject(['3'])->apply($this->filters(), ['manufacturer' => ['3', '99']]);

        self::assertSame(['manufacturer' => ['3']], $result['values'], 'The reachable half survives');
        self::assertSame(['Manufacturer'], $result['adjusted']);
    }

    public function testAFilterWhoseValuesAreAllOutOfReachIsDropped(): void
    {
        $result = $this->subject([])->apply($this->filters(), ['manufacturer' => ['98', '99']]);

        self::assertSame([], $result['values'], 'Better a wider listing than an empty one');
        self::assertSame(['Manufacturer'], $result['adjusted']);
    }

    public function testAFilterThatOffersNoRecordsIsNeverTouched(): void
    {
        $result = $this->subject([])->apply($this->filters(), ['available' => '1']);

        self::assertSame(['available' => '1'], $result['values'], 'A checkbox names no record');
        self::assertSame([], $result['adjusted']);
    }

    public function testAValueWithoutAFilterIsLeftWhereItIs(): void
    {
        $result = $this->subject([])->apply($this->filters(), ['gone' => ['1']]);

        self::assertSame(['gone' => ['1']], $result['values']);
        self::assertSame([], $result['adjusted']);
    }

    public function testEveryAffectedFilterIsNamedOnce(): void
    {
        $filters = $this->filters();
        $filters['dealer'] = new Filter(table: 'tx_test', name: 'dealer', type: 'select', label: 'Dealer');

        $result = $this->subject(['3'])->apply($filters, ['manufacturer' => ['3', '99'], 'dealer' => ['77']]);

        self::assertSame(['Manufacturer', 'Dealer'], $result['adjusted']);
        self::assertSame(['manufacturer' => ['3']], $result['values']);
    }

    public function testANameWithNoFilterBehindItIsReported(): void
    {
        $subject = $this->subject([]);

        self::assertSame(['gone'], $subject->missing($this->filters(), ['manufacturer', 'gone']));
        self::assertSame([], $subject->missing($this->filters(), ['manufacturer', 'available']));
    }

    public function testAMissingFilterIsNamedOnceAndEmptyNamesAreIgnored(): void
    {
        $subject = $this->subject([]);

        self::assertSame(['gone'], $subject->missing($this->filters(), ['gone', 'gone']));
        self::assertSame([], $subject->missing($this->filters(), ['', '']));
    }
}

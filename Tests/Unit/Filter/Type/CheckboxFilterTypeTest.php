<?php

declare(strict_types=1);

namespace DigitalZombies\BeFilter\Tests\Unit\Filter\Type;

use DigitalZombies\BeFilter\Filter\Filter;
use DigitalZombies\BeFilter\Filter\Type\CheckboxFilterType;
use DigitalZombies\BeFilter\Tests\Unit\QueryBuilderTrait;
use PHPUnit\Framework\TestCase;

final class CheckboxFilterTypeTest extends TestCase
{
    use QueryBuilderTrait;

    private CheckboxFilterType $subject;

    protected function setUp(): void
    {
        $this->subject = new CheckboxFilterType();
    }

    public function testOnlyASingleCheckboxIsTaken(): void
    {
        self::assertTrue($this->subject->matches(['type' => 'check'], []));
        self::assertTrue($this->subject->matches(['type' => 'check', 'items' => [['label' => '']]], []));
        self::assertFalse(
            $this->subject->matches(['type' => 'check', 'items' => [['label' => 'a'], ['label' => 'b']]], []),
            'Several items are a bitmask, not a yes or no'
        );
    }

    public function testTheRenderingIsKeptAsATypeOption(): void
    {
        self::assertSame(['checkbox' => false], $this->subject->buildTypeOptions([], []));
        self::assertSame(['checkbox' => true], $this->subject->buildTypeOptions(['render' => 'checkbox'], []));
    }

    public function testADropdownAcceptsBothStatesAndASwitchOnlyTheCheckedOne(): void
    {
        $dropdown = new Filter(table: 'tx_test', name: 'available', type: 'checkbox', label: 'A');
        self::assertSame('1', $this->subject->normalizeValue('1', $dropdown));
        self::assertSame('0', $this->subject->normalizeValue('0', $dropdown));
        self::assertNull($this->subject->normalizeValue('maybe', $dropdown));

        $switch = new Filter(table: 'tx_test', name: 'available', type: 'checkbox', label: 'A', typeOptions: ['checkbox' => true]);
        self::assertSame('1', $this->subject->normalizeValue('1', $switch));
        self::assertNull($this->subject->normalizeValue('0', $switch), 'An unchecked switch filters nothing');
    }

    public function testTheConstraintFollowsTheStateThatWasAskedFor(): void
    {
        $filter = new Filter(table: 'tx_test', name: 'available', type: 'checkbox', label: 'A');

        self::assertSame('tx_test.available = 1', $this->subject->buildConstraint($this->queryBuilder(), $filter, '1'));
        self::assertSame('tx_test.available = 0', $this->subject->buildConstraint($this->queryBuilder(), $filter, '0'));
        self::assertNull($this->subject->buildConstraint($this->queryBuilder(), $filter, 'maybe'));
    }

    public function testACheckboxShowingTheUncheckedStateTurnsTheConstraintAround(): void
    {
        $hidden = new Filter(
            table: 'tx_test',
            name: 'hidden',
            type: 'checkbox',
            label: 'V',
            config: ['items' => [['label' => '', 'invertStateDisplay' => true]]],
        );

        self::assertSame('tx_test.hidden = 0', $this->subject->buildConstraint($this->queryBuilder(), $hidden, '1'), 'Visible means not hidden');
        self::assertSame('tx_test.hidden = 1', $this->subject->buildConstraint($this->queryBuilder(), $hidden, '0'));
    }

    public function testItShipsItsOwnControl(): void
    {
        self::assertSame('@digitalzombies/be_filter/checkbox-filter.js', $this->subject->getJavaScriptModule());
    }
}

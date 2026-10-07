<?php

declare(strict_types=1);

namespace DigitalZombies\BeFilter\Tests\Unit\Event;

use DigitalZombies\BeFilter\Event\ModifyFilterQueryEvent;
use DigitalZombies\BeFilter\Filter\Filter;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

final class ModifyFilterQueryEventTest extends TestCase
{
    public function testTheListenerIsHandedTheQueryTheTableAndTheFilter(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $filter = new Filter(table: 'tx_test', name: 'manufacturer', type: 'select', label: 'M');

        $event = new ModifyFilterQueryEvent($queryBuilder, 'tx_other', $filter);

        self::assertSame($queryBuilder, $event->getQueryBuilder());
        self::assertSame('tx_other', $event->getTable(), 'The queried table, not necessarily the listed one');
        self::assertSame($filter, $event->getFilter());
    }
}

<?php

declare(strict_types=1);

namespace DigitalZombies\BeFilter\Tests\Unit\Event;

use DigitalZombies\BeFilter\Event\ModifyPresetQueryEvent;
use PHPUnit\Framework\TestCase;

final class ModifyPresetQueryEventTest extends TestCase
{
    public function testWithoutAListenerSavedFiltersAreNotScoped(): void
    {
        $event = new ModifyPresetQueryEvent('tx_befilter_preset');

        self::assertSame('tx_befilter_preset', $event->getTable());
        self::assertNull($event->getScopeField());
        self::assertSame(0, $event->getScopeValue());
    }

    public function testAListenerNamesTheColumnEveryRecordIsScopedBy(): void
    {
        $event = new ModifyPresetQueryEvent('tx_befilter_preset');

        $event->setScope('organisation', 3);

        self::assertSame('organisation', $event->getScopeField());
        self::assertSame(3, $event->getScopeValue());
    }
}

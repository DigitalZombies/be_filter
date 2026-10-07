<?php

declare(strict_types=1);

namespace DigitalZombies\BeFilter\Tests\Unit\Filter;

use DigitalZombies\BeFilter\Filter\DatePreset;
use PHPUnit\Framework\TestCase;

final class DatePresetTest extends TestCase
{
    private const NOW = '2026-09-13 15:20:00';

    public function testRangeCoversWholeDays(): void
    {
        [$from, $to] = (new DatePreset('week', 'Week', '-1 week', 'now'))->range(new \DateTimeImmutable(self::NOW));

        self::assertSame('2026-09-06 00:00:00', $from?->format('Y-m-d H:i:s'));
        self::assertSame('2026-09-13 23:59:59', $to?->format('Y-m-d H:i:s'));
    }

    public function testCalendarExpressionsResolve(): void
    {
        [$from, $to] = (new DatePreset('lastMonth', 'Last month', 'first day of last month', 'last day of last month'))
            ->range(new \DateTimeImmutable(self::NOW));

        self::assertSame('2026-08-01 00:00:00', $from?->format('Y-m-d H:i:s'));
        self::assertSame('2026-08-31 23:59:59', $to?->format('Y-m-d H:i:s'));
    }

    public function testRangeStaysOpenWhereNoExpressionIsGiven(): void
    {
        [$from, $to] = (new DatePreset('overdue', 'Overdue', null, 'yesterday'))->range(new \DateTimeImmutable(self::NOW));

        self::assertNull($from);
        self::assertSame('2026-09-12 23:59:59', $to?->format('Y-m-d H:i:s'));

        [$from, $to] = (new DatePreset('since', 'Since', '-3 months', null))->range(new \DateTimeImmutable(self::NOW));

        self::assertSame('2026-06-13 00:00:00', $from?->format('Y-m-d H:i:s'));
        self::assertNull($to);
    }

    public function testEmptyExpressionIsTreatedAsOpen(): void
    {
        [$from, $to] = (new DatePreset('empty', 'Empty', '  ', ''))->range(new \DateTimeImmutable(self::NOW));

        self::assertNull($from);
        self::assertNull($to);
    }

    public function testUnparsableExpressionDoesNotThrow(): void
    {
        [$from, $to] = (new DatePreset('broken', 'Broken', 'rubbish expression', 'now'))
            ->range(new \DateTimeImmutable(self::NOW));

        self::assertNull($from, 'A preset an extension got wrong must not take the listing down');
        self::assertSame('2026-09-13 23:59:59', $to?->format('Y-m-d H:i:s'));
    }
}

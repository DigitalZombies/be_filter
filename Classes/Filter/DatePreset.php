<?php

declare(strict_types=1);

namespace DigitalZombies\BeFilter\Filter;

/**
 * A quick range: a label and two ends, each a relative date expression of PHP. Stored by
 * key, so a saved filter keeps moving with the calendar.
 */
final readonly class DatePreset
{
    public function __construct(
        public string $key,
        public string $label,
        public ?string $from,
        public ?string $to,
    ) {}

    /**
     * @return array{0: ?\DateTimeImmutable, 1: ?\DateTimeImmutable}
     */
    public function range(\DateTimeImmutable $now): array
    {
        return [
            $this->boundary($now, $this->from, false),
            $this->boundary($now, $this->to, true),
        ];
    }

    /**
     * Boundaries cover whole days.
     */
    private function boundary(\DateTimeImmutable $now, ?string $expression, bool $endOfDay): ?\DateTimeImmutable
    {
        if ($expression === null || trim($expression) === '') {
            return null;
        }

        try {
            $date = $now->setTime(0, 0)->modify($expression);
        } catch (\Throwable) {
            // A preset an extension got wrong must not take the listing down.
            return null;
        }

        return $endOfDay ? $date->setTime(23, 59, 59) : $date->setTime(0, 0);
    }
}

<?php

namespace N2ns\Stats\Reports;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/** An inclusive range of site-local dates, at most MAX_DAYS long. */
final readonly class Period
{
    public const MAX_DAYS = 366;

    public const PRESETS = ['today', 'yesterday', '7d', '30d', '90d', 'month', 'last_month'];

    public function __construct(public CarbonImmutable $from, public CarbonImmutable $to) {}

    /** Parses YYYY-MM-DD dates; throws InvalidArgumentException when invalid, reversed or too long. */
    public static function parse(string $from, string $to): self
    {
        $period = new self(self::date($from, 'from'), self::date($to, 'to'));
        if ($period->from->greaterThan($period->to)) {
            throw new InvalidArgumentException('The from date must not be after the to date.');
        }
        if ($period->days() > self::MAX_DAYS) {
            throw new InvalidArgumentException('The range must not exceed '.self::MAX_DAYS.' days.');
        }

        return $period;
    }

    /** The last $days days up to and including today in $timezone. */
    public static function lastDays(int $days, string $timezone): self
    {
        $today = self::today($timezone);

        return new self($today->subDays($days - 1), $today);
    }

    public static function preset(string $name, string $timezone): self
    {
        $today = self::today($timezone);

        return match ($name) {
            'today' => new self($today, $today),
            'yesterday' => new self($today->subDay(), $today->subDay()),
            '7d' => self::lastDays(7, $timezone),
            '30d' => self::lastDays(30, $timezone),
            '90d' => self::lastDays(90, $timezone),
            'month' => new self($today->startOfMonth(), $today),
            'last_month' => new self($today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()->startOfDay()),
            default => throw new InvalidArgumentException("Unknown range [{$name}]."),
        };
    }

    public function days(): int
    {
        return (int) round($this->from->diffInDays($this->to, true)) + 1;
    }

    /** The period of equal length that ends the day before this one starts. */
    public function previous(): self
    {
        $to = $this->from->subDay();

        return new self($to->subDays($this->days() - 1), $to);
    }

    private static function today(string $timezone): CarbonImmutable
    {
        return self::date(CarbonImmutable::now($timezone)->toDateString(), 'today');
    }

    private static function date(string $value, string $field): CarbonImmutable
    {
        $date = null;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            try {
                $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, 'UTC');
            } catch (\Throwable) {
                $date = null;
            }
        }
        if (! $date instanceof CarbonImmutable || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException("The {$field} date must be a valid YYYY-MM-DD date.");
        }

        return $date;
    }
}

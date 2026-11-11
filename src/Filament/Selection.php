<?php

namespace N2ns\Stats\Filament;

use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentColor;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;
use N2ns\Stats\Models\Site;
use N2ns\Stats\Reports\Period;

/**
 * The site and date range chosen in the filters of the statistics page (filters[site], filters[range], filters[from],
 * filters[to]). An unknown site falls back to the first one, an unknown range to the last 30 days, and an invalid
 * custom range (see Period::parse()) to the last 30 days with an error message.
 */
final readonly class Selection
{
    public const DEFAULT_RANGE = '30d';

    public function __construct(public Site $site, public string $range, public Period $period, public ?string $error = null) {}

    /** @return Collection<int, Site> */
    public static function sites(): Collection
    {
        return Site::query()->orderBy('name')->orderBy('id')->get();
    }

    /**
     * Null when there are no sites.
     *
     * @param  array<string, mixed>|null  $filters
     */
    public static function fromFilters(?array $filters): ?self
    {
        $value = fn (string $key): string => is_string($filters[$key] ?? null) ? $filters[$key] : '';

        $sites = self::sites();
        $site = ($value('site') !== '' ? $sites->firstWhere('public_id', $value('site')) : null) ?? $sites->first();
        if ($site === null) {
            return null;
        }

        $range = $value('range');
        if ($range === 'custom') {
            try {
                return new self($site, 'custom', Period::parse($value('from'), $value('to')));
            } catch (InvalidArgumentException) {
                return new self($site, self::DEFAULT_RANGE, Period::preset(self::DEFAULT_RANGE, $site->timezone),
                    (string) __('stats::filament.range_invalid', ['max' => Period::MAX_DAYS]));
            }
        }
        if (! in_array($range, Period::PRESETS, true)) {
            $range = self::DEFAULT_RANGE;
        }

        return new self($site, $range, Period::preset($range, $site->timezone));
    }

    /**
     * Colours (shade 500) for chart series and slices: the panel's primary colour, then distinct hues.
     *
     * @return list<string>
     */
    public static function colors(): array
    {
        // The panel's primary colour first, then distinct hues (a theme may give several semantic names one colour).
        $colors = [(FilamentColor::getColor('primary') ?? Color::Blue)[500]];
        foreach ([Color::Blue, Color::Emerald, Color::Violet, Color::Rose, Color::Cyan, Color::Lime, Color::Orange] as $palette) {
            if (! in_array($palette[500], $colors, true)) {
                $colors[] = $palette[500];
            }
        }

        return array_slice($colors, 0, 6);
    }
}

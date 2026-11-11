<?php

namespace N2ns\Stats\Filament\Widgets\Report;

use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Facades\Gate;
use N2ns\Stats\Filament\Selection;

/** A widget of the statistics page: shows the site and range chosen in the page filters; needs the viewStats gate. */
trait ReadsSelection
{
    use InteractsWithPageFilters;

    public static function canView(): bool
    {
        return Gate::allows('viewStats');
    }

    protected function selection(): ?Selection
    {
        return Selection::fromFilters($this->pageFilters);
    }
}

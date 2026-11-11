<?php

namespace N2ns\Stats\Filament\Widgets;

use Filament\Support\Enums\Alignment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\Gate;
use N2ns\Stats\Filament\Pages\StatsDashboard;
use N2ns\Stats\Filament\Selection;
use N2ns\Stats\Http\View\Dashboard;
use N2ns\Stats\Http\View\Format;
use N2ns\Stats\Reports\Period;
use N2ns\Stats\Reports\StatsQuery;

/** Per site: the top 5 traffic sources of the last 7 days. */
class TopSources extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Gate::allows('viewStats');
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('stats::filament.top_sources'))
            ->records(fn (): array => $this->rows())
            ->columns([
                TextColumn::make('site')->label(__('stats::filament.site'))
                    ->url(fn (array $record): string => StatsDashboard::urlFor($record['public_id'], '7d')),
                TextColumn::make('source')->label(__('stats::reports.columns.source'))
                    ->state(fn (array $record): string => $record['source'] ?? __('stats::filament.direct')),
                TextColumn::make('source_type')->label(__('stats::reports.columns.source_type'))
                    ->state(fn (array $record): string => Dashboard::text('sources', 'source_type', $record['source_type'])),
                TextColumn::make('visits')->label(__('stats::reports.columns.visits'))
                    ->formatStateUsing(fn (int $state): string => Format::number($state))->alignment(Alignment::End),
                TextColumn::make('visitors')->label(__('stats::reports.columns.visitors'))
                    ->formatStateUsing(fn (int $state): string => Format::number($state))->alignment(Alignment::End),
            ])
            ->paginated(false)
            ->emptyStateHeading(__('stats::filament.no_visits'));
    }

    /** @return array<string, array<string, mixed>> */
    private function rows(): array
    {
        $stats = app(StatsQuery::class);
        $rows = [];
        foreach (Selection::sites() as $site) {
            $period = Period::preset('7d', $site->timezone);
            foreach ($stats->breakdown($site, 'sources', $period->from, $period->to, 5) as $i => $row) {
                $rows[$site->public_id.'-'.$i] = ['site' => $site->name, 'public_id' => $site->public_id] + $row;
            }
        }

        return $rows;
    }
}

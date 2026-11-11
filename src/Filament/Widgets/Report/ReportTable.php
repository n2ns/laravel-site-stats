<?php

namespace N2ns\Stats\Filament\Widgets\Report;

use Filament\Actions\Action;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use N2ns\Stats\Http\View\Dashboard;
use N2ns\Stats\Http\View\Format;
use N2ns\Stats\Reports\StatsQuery;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * One report of the selected range as a table. In the default order (main metric, descending) each page is read with
 * breakdown offsets; sorting by another column or searching works on the first SORT_LIMIT rows.
 */
class ReportTable extends TableWidget
{
    use ReadsSelection;

    // Part of the statistics page only, not of the panel's widget discovery (declared here, not in the trait:
    // PHP before 8.5 rejects a trait property whose default differs from the parent's).
    protected static bool $isDiscovered = false;

    // Loaded when its tab is shown, so hidden tabs cost nothing on page load.
    protected static ?bool $isLazyLoaded = true;

    public const PER_PAGE = 25;

    public const SORT_LIMIT = 5000;

    /** Rows in a CSV export, as in the standalone dashboard. */
    public const EXPORT_LIMIT = 500;

    /** Text columns that make a report searchable. */
    private const SEARCH_COLUMNS = ['path', 'from_path', 'to_path', 'source', 'target'];

    #[Locked]
    public string $report = 'pages';

    protected int|string|array $columnSpan = 'full';

    private ?int $total = null;

    public function table(Table $table): Table
    {
        $report = in_array($this->report, StatsQuery::REPORTS, true) ? $this->report : 'pages';
        $metric = StatsQuery::mainMetric($report);
        $columns = [];
        foreach (StatsQuery::COLUMNS[$report] as $column) {
            $columns[] = self::column($report, $column);
        }
        $columns[] = TextColumn::make('share')
            ->label(__('stats::filament.share'))
            ->state(fn (array $record): float => $this->total($report) > 0 ? (int) $record[$metric] / $this->total($report) : 0.0)
            ->formatStateUsing(fn (float $state): string => Format::percent($state))
            ->alignment(Alignment::End);

        return $table
            ->heading(__('stats::reports.titles.'.$report))
            ->queryStringIdentifier($report)
            ->records(fn (int $page, ?string $sortColumn, ?string $sortDirection, ?string $search): Paginator => $this->rows($report, $page, $sortColumn, $sortDirection, $search))
            ->columns($columns)
            ->defaultSort($metric, 'desc')
            ->searchable(array_intersect(self::SEARCH_COLUMNS, StatsQuery::COLUMNS[$report]) !== [])
            ->paginated([self::PER_PAGE])
            ->defaultPaginationPageOption(self::PER_PAGE)
            ->emptyStateHeading(__('stats::filament.no_data'))
            ->emptyStateIcon(Heroicon::OutlinedChartBar)
            ->headerActions([
                Action::make('export')
                    ->label(__('stats::filament.csv'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('gray')
                    ->authorize(fn (): bool => Gate::allows('viewStats'))
                    ->action(fn (): ?StreamedResponse => $this->export($report)),
            ]);
    }

    private static function column(string $report, string $column): TextColumn
    {
        $text = TextColumn::make($column)->label(Dashboard::header($report, $column))->sortable();
        if (Dashboard::isText($column)) {
            return $text->state(fn (array $record): string => Dashboard::text($report, $column, $record[$column] === null ? null : (string) $record[$column]))
                ->wrap();
        }

        return $text->alignment(Alignment::End)->formatStateUsing(fn (int|float $state): string => match (true) {
            Dashboard::isRate($column), $column === 'bounce_rate' => Format::percent($state),
            $column === 'avg_seconds' => Format::duration((int) $state),
            default => Format::number($state),
        });
    }

    private function total(string $report): int
    {
        if ($this->total === null) {
            $selection = $this->selection();
            $this->total = $selection === null ? 0 : app(StatsQuery::class)->total($selection->site, $report, $selection->period->from, $selection->period->to);
        }

        return $this->total;
    }

    /** @return Paginator<array-key, array<string, int|float|string|null>> */
    private function rows(string $report, int $page, ?string $sortColumn, ?string $sortDirection, ?string $search): Paginator
    {
        $page = max(1, $page);
        $offset = ($page - 1) * self::PER_PAGE;
        $options = ['path' => Paginator::resolveCurrentPath(), 'pageName' => $this->getTablePaginationPageName()];
        $selection = $this->selection();
        if ($selection === null) {
            return new Paginator([], self::PER_PAGE, $page, $options);
        }
        [$site, $from, $to] = [$selection->site, $selection->period->from, $selection->period->to];
        $stats = app(StatsQuery::class);
        $metric = StatsQuery::mainMetric($report);
        $search = trim((string) $search);
        $sortColumn = in_array($sortColumn, StatsQuery::COLUMNS[$report], true) ? $sortColumn : null;

        if ($search === '' && ($sortColumn === null || ($sortColumn === $metric && $sortDirection !== 'asc'))) {
            // One row more than a page tells the paginator whether a next page exists.
            $rows = collect($stats->breakdown($site, $report, $from, $to, self::PER_PAGE + 1, $offset));
        } else {
            $rows = collect($stats->breakdown($site, $report, $from, $to, self::SORT_LIMIT));
            if ($search !== '') {
                $needle = mb_strtolower($search);
                $rows = $rows->filter(function (array $row) use ($report, $needle): bool {
                    foreach (StatsQuery::COLUMNS[$report] as $column) {
                        if (Dashboard::isText($column) && str_contains(mb_strtolower(Dashboard::text($report, $column, $row[$column] === null ? null : (string) $row[$column])), $needle)) {
                            return true;
                        }
                    }

                    return false;
                });
            }
            if ($sortColumn !== null) {
                $rows = self::sort($rows->values(), $sortColumn, $sortDirection === 'desc');
            }
            $rows = $rows->values()->slice($offset, self::PER_PAGE + 1);
        }

        return new Paginator($rows->values()->mapWithKeys(fn (array $row, int $i): array => [(string) ($offset + $i) => $row]), self::PER_PAGE, $page, $options);
    }

    /**
     * @param  Collection<int, array<string, int|float|string|null>>  $rows
     * @return Collection<int, array<string, int|float|string|null>>
     */
    private static function sort(Collection $rows, string $column, bool $descending): Collection
    {
        $sorted = $rows->all();
        usort($sorted, function (array $a, array $b) use ($column, $descending): int {
            $order = is_string($a[$column]) || is_string($b[$column])
                ? strnatcasecmp((string) $a[$column], (string) $b[$column])
                : $a[$column] <=> $b[$column];

            return $descending ? -$order : $order;
        });

        return collect($sorted);
    }

    /** The report as CSV, like the standalone dashboard's export: raw values, formulas neutralised. */
    private function export(string $report): ?StreamedResponse
    {
        $selection = $this->selection();
        if ($selection === null) {
            return null;
        }
        [$site, $from, $to] = [$selection->site, $selection->period->from, $selection->period->to];
        $rows = app(StatsQuery::class)->breakdown($site, $report, $from, $to, self::EXPORT_LIMIT);
        $filename = sprintf('stats-%s-%s-%s-%s.csv', $site->public_id, $report, $from->toDateString(), $to->toDateString());

        return response()->streamDownload(function () use ($report, $rows): void {
            $out = fopen('php://output', 'w');
            assert($out !== false);
            fputcsv($out, StatsQuery::COLUMNS[$report], escape: '');
            foreach ($rows as $row) {
                fputcsv($out, array_map(self::csvCell(...), array_values($row)), escape: '');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private static function csvCell(int|float|string|null $value): int|float|string|null
    {
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }
}

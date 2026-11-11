<?php

namespace N2ns\Stats\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use N2ns\Stats\Http\View\Dashboard;
use N2ns\Stats\Http\View\Page;
use N2ns\Stats\Models\Site;
use N2ns\Stats\Reports\StatsQuery;
use Symfony\Component\HttpFoundation\Response;

class DashboardController
{
    public const EXPORT_LIMIT = 500;

    public function __construct(private StatsQuery $stats) {}

    /** The first site's dashboard (or the one picked in the site switcher), or the site manager when there is none. */
    public function index(Request $request): RedirectResponse
    {
        $picked = self::query($request, 'site');
        $site = $picked !== '' ? Site::query()->where('public_id', $picked)->first() : null;
        $site ??= Site::query()->orderBy('name')->orderBy('id')->first();

        if ($site === null) {
            return redirect()->route('stats.sites.index');
        }

        return redirect()->route('stats.dashboard.site', ['publicId' => $site->public_id] + $request->only('range', 'from', 'to'));
    }

    public function show(Request $request, string $publicId): View
    {
        $site = $this->site($publicId);
        $data = Dashboard::data($this->stats, $site, self::query($request, 'range'), self::query($request, 'from'), self::query($request, 'to'));

        return Page::make($site->name, 'stats::dashboard', $data + [
            'sites' => Site::query()->orderBy('name')->orderBy('id')->get(),
            'siteAction' => route('stats.dashboard'),
            'rangeAction' => route('stats.dashboard.site', ['publicId' => $site->public_id]),
            'rangeHidden' => [],
        ]);
    }

    /** CSV of one report for the selected range (up to EXPORT_LIMIT rows). */
    public function export(Request $request, string $publicId, string $report): Response
    {
        $site = $this->site($publicId);
        abort_unless(in_array($report, StatsQuery::REPORTS, true), 404);
        [, $period] = Dashboard::period($site, self::query($request, 'range'), self::query($request, 'from'), self::query($request, 'to'));

        $rows = $this->stats->breakdown($site, $report, $period->from, $period->to, self::EXPORT_LIMIT);

        $out = fopen('php://temp', 'r+');
        assert($out !== false);
        fputcsv($out, StatsQuery::COLUMNS[$report], escape: '');
        foreach ($rows as $row) {
            fputcsv($out, array_map($this->csvCell(...), array_values($row)), escape: '');
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        $filename = sprintf('stats-%s-%s-%s-%s.csv', $site->public_id, $report, $period->from->toDateString(), $period->to->toDateString());

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function site(string $publicId): Site
    {
        return Site::query()->where('public_id', $publicId)->firstOrFail();
    }

    /** A query parameter as a string; '' when missing or not a string (e.g. ?range[]=x). */
    private static function query(Request $request, string $key): string
    {
        $value = $request->query($key);

        return is_string($value) ? $value : '';
    }

    /** Neutralises spreadsheet formulas in visitor-supplied text (paths, labels, UTM values). */
    private function csvCell(int|float|string|null $value): int|float|string|null
    {
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }
}

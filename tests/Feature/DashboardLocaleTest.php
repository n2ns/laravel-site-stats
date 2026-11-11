<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use N2ns\Stats\Reports\StatsQuery;
use N2ns\Stats\Tests\StatsSeeder as S;

beforeEach(function () {
    Gate::define('viewStats', fn ($user = null) => true);
    Carbon::setTestNow('2026-03-10 12:00:00');
});

it('has the same translation keys in English and Simplified Chinese', function (string $file) {
    $keys = function (array $array, string $prefix = '') use (&$keys): array {
        $out = [];
        foreach ($array as $key => $value) {
            $out = [...$out, ...(is_array($value) ? $keys($value, $prefix.$key.'.') : [$prefix.$key])];
        }

        return $out;
    };
    $en = require __DIR__.'/../../lang/en/'.$file.'.php';
    $zh = require __DIR__.'/../../lang/zh_CN/'.$file.'.php';

    expect($keys($zh))->toEqualCanonicalizing($keys($en));
})->with(['dashboard', 'reports', 'sites', 'filament']);

it('translates every report title and column', function () {
    foreach (['en', 'zh_CN'] as $locale) {
        app()->setLocale($locale);
        foreach (StatsQuery::REPORTS as $report) {
            expect(__('stats::reports.titles.'.$report))->not->toStartWith('stats::');
            foreach (StatsQuery::COLUMNS[$report] as $column) {
                expect(__('stats::reports.columns.'.$column))->not->toStartWith('stats::');
            }
        }
    }
});

it('renders the dashboard in Simplified Chinese', function () {
    app()->setLocale('zh_CN');
    $site = S::site('示例站点');
    S::totals($site, '2026-03-09', ['visitors' => 20, 'visits' => 20, 'pageviews' => 40]);
    S::totals($site, '2026-03-02', ['visitors' => 10, 'visits' => 10, 'pageviews' => 40]);
    S::source($site, '2026-03-09', 'search', 'google.com', ['visits' => 20, 'visitors' => 20, 'pageviews' => 40, 'bounces' => 0]);

    $this->get(route('stats.dashboard.site', ['publicId' => $site->public_id, 'range' => '7d']))
        ->assertOk()
        ->assertSee('<html lang="zh-CN">', false)
        ->assertSee('最近 15 天')
        ->assertSee('对比：3月4日–9日 与 2月26日 – 3月3日（只比较完整的日子）。')
        ->assertSee('今天尚未结束')
        ->assertSeeInOrder(['访客', '20', '+100.0%', '访问次数', '浏览量', '被浏览的页面', '每次访问浏览页数', '跳出率', '平均访问时长', '事件', '外链点击', '实时'])
        ->assertSeeInOrder(['页面', '来源', '访客', '事件', 'AI'])
        ->assertSee('热门页面')
        ->assertSee('来源类型')
        ->assertSee('搜索引擎')
        ->assertSee('该时间范围内没有数据。')
        ->assertSee('占比')
        ->assertSee('不使用 Cookie')
        ->assertDontSee('stats::')
        ->assertDontSee('Last 15 days')
        ->assertDontSee('Top pages');
});

it('shows the invalid range message in Simplified Chinese', function () {
    app()->setLocale('zh_CN');
    $site = S::site();
    $this->get(route('stats.dashboard.site', ['publicId' => $site->public_id, 'range' => 'custom', 'from' => 'x', 'to' => 'y']))
        ->assertSee('自定义范围无效')
        ->assertSee('现改为显示最近 30 天。');
});

it('renders the site manager in Simplified Chinese', function () {
    app()->setLocale('zh_CN');
    S::site('Example');

    $this->get(route('stats.sites.index'))
        ->assertOk()
        ->assertSee('新建站点')
        ->assertSee('打开统计面板')
        ->assertSee('公开计数')
        ->assertSee('生成服务器密钥')
        ->assertDontSee('stats::');
});

it('shows site form errors and flash messages in Simplified Chinese', function () {
    app()->setLocale('zh_CN');

    $this->followingRedirects()->from(route('stats.sites.index'))
        ->post(route('stats.sites.store'), ['name' => '', 'domains' => 'not a host', 'timezone' => 'UTC', 'locales' => 'english'])
        ->assertSee('请填写名称。')
        ->assertSee('“not a host”不是有效的主机名', false)
        ->assertSee('“english”不是有效的语言代码', false);

    $this->followingRedirects()
        ->post(route('stats.sites.store'), ['name' => '博客', 'domains' => 'example.com', 'timezone' => 'UTC'])
        ->assertSee('已创建站点“博客”。', false);
});

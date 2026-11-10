<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use N2ns\Stats\Models\Site;
use N2ns\Stats\Support\VisitorHasher;

it('hashes with a per-date salt created on first use', function () {
    $site = Site::create(['name' => 'A', 'domains' => ['a.com']]);
    $hasher = new VisitorHasher;
    $day = CarbonImmutable::parse('2026-11-11');

    $h1 = $hasher->hash($site, '1.2.3.4', 'UA', $day);
    $salt = DB::table('stats_salts')->where('date', '2026-11-11')->value('salt');

    expect($salt)->toMatch('/^[0-9a-f]{64}$/')
        ->and($h1)->toBe(hash('sha256', $salt.$site->public_id.'1.2.3.4UA'))
        ->and($hasher->hash($site, '1.2.3.4', 'UA', $day))->toBe($h1)
        ->and((new VisitorHasher)->hash($site, '1.2.3.4', 'UA', $day))->toBe($h1)
        ->and($hasher->hash($site, '1.2.3.5', 'UA', $day))->not->toBe($h1)
        ->and(DB::table('stats_salts')->count())->toBe(1);
});

it('rotates the salt every day and differs per site', function () {
    $a = Site::create(['name' => 'A', 'domains' => ['a.com']]);
    $b = Site::create(['name' => 'B', 'domains' => ['b.com']]);
    $hasher = new VisitorHasher;

    $today = $hasher->hash($a, '1.2.3.4', 'UA', CarbonImmutable::parse('2026-11-11'));
    $tomorrow = $hasher->hash($a, '1.2.3.4', 'UA', CarbonImmutable::parse('2026-11-12'));

    expect($tomorrow)->not->toBe($today)
        ->and($hasher->hash($b, '1.2.3.4', 'UA', CarbonImmutable::parse('2026-11-11')))->not->toBe($today)
        ->and(DB::table('stats_salts')->count())->toBe(2);
});

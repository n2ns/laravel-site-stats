<?php

use Illuminate\Support\Facades\Schema;
use N2ns\Stats\Models\Site;

it('migrates and creates a site with a public id', function () {
    expect(Schema::hasTable('stats_daily_totals'))->toBeTrue();
    $site = Site::create(['name' => 'Example', 'domains' => ['example.com']]);
    expect($site->public_id)->toHaveLength(26)
        ->and($site->allowsHost('www.example.com'))->toBeTrue()
        ->and($site->allowsHost('evil.com'))->toBeFalse();
});

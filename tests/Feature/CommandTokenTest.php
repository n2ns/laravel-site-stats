<?php

use Illuminate\Support\Facades\Artisan;
use N2ns\Stats\Models\ApiToken;
use N2ns\Stats\Models\Site;

it('creates a token for all sites, shown once and stored as a hash', function () {
    expect(Artisan::call('stats:token', ['action' => 'create', '--name' => 'Reports']))->toBe(0);

    preg_match('/^\s*([A-Za-z0-9]{48})\s*$/m', Artisan::output(), $m);
    $token = ApiToken::sole();
    expect($m[1] ?? null)->not->toBeNull()
        ->and($token->name)->toBe('Reports')
        ->and($token->site_ids)->toBeNull()
        ->and($token->token_hash)->toBe(hash('sha256', $m[1]))
        ->and(ApiToken::findByPlain($m[1])?->id)->toBe($token->id);
});

it('scopes a token to the given sites', function () {
    $a = Site::create(['name' => 'A', 'domains' => ['a.com']]);
    $b = Site::create(['name' => 'B', 'domains' => ['b.com']]);

    $this->artisan('stats:token', ['action' => 'create', '--name' => 'A only', '--site' => [$a->public_id]])->assertSuccessful();

    $token = ApiToken::sole();
    expect($token->site_ids)->toBe([$a->id])
        ->and($token->canAccess($a))->toBeTrue()
        ->and($token->canAccess($b))->toBeFalse();
});

it('rejects a token without a name or with an unknown site', function () {
    $this->artisan('stats:token', ['action' => 'create'])->assertFailed();
    $this->artisan('stats:token', ['action' => 'create', '--name' => 'X', '--site' => ['missing']])->assertFailed();

    expect(ApiToken::count())->toBe(0);
});

it('lists tokens without their secrets', function () {
    $site = Site::create(['name' => 'A', 'domains' => ['a.com']]);
    ApiToken::create(['name' => 'All', 'token_hash' => hash('sha256', 'secret-one')]);
    ApiToken::create(['name' => 'Scoped', 'token_hash' => hash('sha256', 'secret-two'), 'site_ids' => [$site->id]]);

    expect(Artisan::call('stats:token', ['action' => 'list']))->toBe(0);
    $output = Artisan::output();

    expect($output)->toContain('All')->toContain('Scoped')->toContain($site->public_id)->toContain('never')
        ->not->toContain(hash('sha256', 'secret-one'))
        ->not->toContain('secret-one');
});

it('revokes a token by id', function () {
    $keep = ApiToken::create(['name' => 'Keep', 'token_hash' => hash('sha256', 'a')]);
    $drop = ApiToken::create(['name' => 'Drop', 'token_hash' => hash('sha256', 'b')]);

    $this->artisan('stats:token', ['action' => 'revoke', '--id' => (string) $drop->id])->assertSuccessful();

    expect(ApiToken::pluck('id')->all())->toBe([$keep->id]);
    $this->artisan('stats:token', ['action' => 'revoke', '--id' => (string) $drop->id])->assertFailed();
    $this->artisan('stats:token', ['action' => 'revoke'])->assertFailed();
});

it('fails for an unknown action', function () {
    $this->artisan('stats:token', ['action' => 'rotate'])->assertFailed();
});

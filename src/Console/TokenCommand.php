<?php

namespace N2ns\Stats\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use N2ns\Stats\Models\ApiToken;
use N2ns\Stats\Models\Site;

class TokenCommand extends Command
{
    protected $signature = 'stats:token
        {action : create|list|revoke}
        {--name= : Token name}
        {--site=* : Public id of a site the token may read (repeatable; none = all sites)}
        {--id= : Token id (revoke)}';

    protected $description = 'Manage the read API tokens of N2N Stats';

    public function handle(): int
    {
        return match ($this->argument('action')) {
            'create' => $this->create(),
            'list' => $this->list(),
            'revoke' => $this->revoke(),
            default => $this->fail('Unknown action. Use create, list or revoke.'),
        };
    }

    private function create(): int
    {
        $name = is_string($this->option('name')) ? trim($this->option('name')) : '';
        if ($name === '' || mb_strlen($name) > 120) {
            return $this->fail('--name is required (at most 120 characters).');
        }

        $siteIds = null;
        $publicIds = array_values(array_unique(array_filter((array) $this->option('site'), fn ($id) => is_string($id) && $id !== '')));
        if ($publicIds !== []) {
            $sites = Site::query()->whereIn('public_id', $publicIds)->pluck('id', 'public_id');
            $missing = array_diff($publicIds, $sites->keys()->all());
            if ($missing !== []) {
                return $this->fail('Unknown site: '.implode(', ', $missing));
            }
            $siteIds = array_map('intval', $sites->values()->all());
        }

        $plain = Str::random(48);
        $token = ApiToken::create(['name' => $name, 'token_hash' => hash('sha256', $plain), 'site_ids' => $siteIds]);

        $this->components->info("Token #{$token->id} created. It is shown only once:");
        $this->newLine();
        $this->line($plain);
        $this->newLine();
        $this->line('Use it as: Authorization: Bearer <token>');

        return self::SUCCESS;
    }

    private function list(): int
    {
        $tokens = ApiToken::query()->orderBy('id')->get();
        if ($tokens->isEmpty()) {
            $this->components->info('No API tokens.');

            return self::SUCCESS;
        }

        $publicIds = Site::query()->pluck('public_id', 'id');
        $this->table(
            ['Id', 'Name', 'Sites', 'Last used', 'Created'],
            $tokens->map(fn (ApiToken $token) => [
                $token->id,
                $token->name,
                $token->site_ids === null
                    ? 'all'
                    : implode(', ', array_map(fn (int $id) => $publicIds[$id] ?? "#{$id} (deleted)", $token->site_ids)),
                (string) ($token->last_used_at ?? 'never'),
                (string) $token->getAttribute('created_at'),
            ])->all(),
        );

        return self::SUCCESS;
    }

    private function revoke(): int
    {
        $id = $this->option('id');
        if (! is_string($id) || ! ctype_digit($id)) {
            return $this->fail('--id=<token id> is required.');
        }

        $token = ApiToken::query()->find((int) $id);
        if ($token === null) {
            return $this->fail("No token with id {$id}.");
        }

        $token->delete();
        $this->components->info("Token #{$id} revoked.");

        return self::SUCCESS;
    }
}

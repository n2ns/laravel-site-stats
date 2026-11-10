<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $p = config('stats.table_prefix', 'stats_');

        Schema::create($p.'sites', function (Blueprint $t) {
            $t->id();
            $t->string('public_id', 26)->unique();
            $t->string('name', 120);
            $t->json('domains');
            $t->string('timezone', 64)->default('UTC');
            $t->string('server_key_hash', 64)->nullable();
            $t->timestamps();
        });

        Schema::create($p.'api_tokens', function (Blueprint $t) {
            $t->id();
            $t->string('name', 120);
            $t->string('token_hash', 64)->unique();
            $t->json('site_ids')->nullable();
            $t->timestamp('last_used_at')->nullable();
            $t->timestamps();
        });

        // Temporary data (deleted within two days by stats:maintain).
        Schema::create($p.'salts', function (Blueprint $t) {
            $t->date('date')->primary();
            $t->string('salt', 64);
        });

        Schema::create($p.'uniques', function (Blueprint $t) {
            $t->unsignedBigInteger('site_id');
            $t->date('date');
            $t->string('scope', 64);
            $t->string('visitor_hash', 64);
            $t->primary(['site_id', 'date', 'scope', 'visitor_hash']);
            $t->index('date');
        });

        Schema::create($p.'visits', function (Blueprint $t) {
            $t->unsignedBigInteger('site_id');
            $t->string('visitor_hash', 64);
            $t->dateTime('started_at');
            $t->date('date');
            $t->dateTime('last_seen_at');
            $t->unsignedInteger('pageviews')->default(0);
            $t->string('entry_path_hash', 64);
            $t->string('exit_path_hash', 64);
            $t->string('source_key', 64);
            $t->primary(['site_id', 'visitor_hash', 'started_at']);
            $t->index(['site_id', 'last_seen_at']);
            $t->index('last_seen_at');
        });

        // Daily aggregates (kept for retention_days, forever by default).
        Schema::create($p.'daily_totals', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('site_id');
            $t->date('date');
            $t->unsignedBigInteger('pageviews')->default(0);
            $t->unsignedBigInteger('visitors')->default(0);
            $t->unsignedBigInteger('visits')->default(0);
            $t->unsignedBigInteger('bounces')->default(0);
            $t->unsignedBigInteger('duration_seconds')->default(0);
            $t->unsignedBigInteger('events')->default(0);
            $t->unique(['site_id', 'date']);
        });

        Schema::create($p.'daily_pages', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('site_id');
            $t->date('date');
            $t->string('path', 512);
            $t->string('path_hash', 64);
            $t->unsignedBigInteger('pageviews')->default(0);
            $t->unsignedBigInteger('visitors')->default(0);
            $t->unsignedBigInteger('entries')->default(0);
            $t->unsignedBigInteger('exits')->default(0);
            $t->unique(['site_id', 'date', 'path_hash']);
        });

        Schema::create($p.'daily_sources', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('site_id');
            $t->date('date');
            $t->string('source_key', 64);
            $t->string('source_type', 20);
            $t->string('source', 191)->nullable();
            $t->string('utm_medium', 128)->nullable();
            $t->string('utm_campaign', 128)->nullable();
            $t->string('utm_content', 128)->nullable();
            $t->string('utm_term', 128)->nullable();
            $t->unsignedBigInteger('visits')->default(0);
            $t->unsignedBigInteger('visitors')->default(0);
            $t->unsignedBigInteger('pageviews')->default(0);
            $t->unsignedBigInteger('bounces')->default(0);
            $t->unique(['site_id', 'date', 'source_key']);
        });

        Schema::create($p.'daily_dimensions', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('site_id');
            $t->date('date');
            $t->string('dimension', 20);
            $t->string('value', 64);
            $t->unsignedBigInteger('visits')->default(0);
            $t->unsignedBigInteger('visitors')->default(0);
            $t->unique(['site_id', 'date', 'dimension', 'value']);
        });

        Schema::create($p.'daily_events', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('site_id');
            $t->date('date');
            $t->string('kind', 20);
            $t->string('name', 64);
            $t->string('target', 2048)->nullable();
            $t->string('target_hash', 64);
            $t->string('path', 512)->nullable();
            $t->string('path_hash', 64);
            $t->unsignedBigInteger('count')->default(0);
            $t->unsignedBigInteger('visitors')->default(0);
            $t->unique(['site_id', 'date', 'kind', 'name', 'target_hash', 'path_hash'], 'stats_daily_events_unique');
        });
    }

    public function down(): void
    {
        $p = config('stats.table_prefix', 'stats_');
        foreach (['daily_events', 'daily_dimensions', 'daily_sources', 'daily_pages', 'daily_totals', 'visits', 'uniques', 'salts', 'api_tokens', 'sites'] as $table) {
            Schema::dropIfExists($p.$table);
        }
    }
};

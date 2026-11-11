<?php

namespace N2ns\Stats\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use N2ns\Stats\StatsServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    /** Extra config applied before the package boots (routes depend on it); set in beforeAll, reset in afterAll. */
    public static array $config = [];

    protected function getPackageProviders($app): array
    {
        return [StatsServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', env('DB_CONNECTION', 'testing'));
        if (env('DB_SEARCH_PATH')) {
            $app['config']->set('database.connections.pgsql.search_path', env('DB_SEARCH_PATH'));
        }
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        foreach (static::$config as $key => $value) {
            $app['config']->set($key, $value);
        }
    }
}

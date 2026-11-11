<?php

use Illuminate\Support\Facades\DB;
use N2ns\Stats\Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

/**
 * Makes every INSERT or UPDATE on $table fail, to test write-failure handling without changing the schema
 * (on MySQL a DROP inside the test transaction commits it implicitly and removes all savepoints).
 */
function failWritesTo(string $table): void
{
    DB::beforeExecuting(function (string $query) use ($table): void {
        if (preg_match('/^\s*(insert|update)\b/i', $query) === 1 && str_contains($query, $table)) {
            throw new RuntimeException('Forced write failure.');
        }
    });
}

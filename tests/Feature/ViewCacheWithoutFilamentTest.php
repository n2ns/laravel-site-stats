<?php

use Illuminate\Support\Facades\View;
use Symfony\Component\Finder\Finder;

// `php artisan view:cache` compiles every view of every registered namespace. A host without Filament cannot compile
// Filament's components, so the views that use them live in their own namespace, registered only with Filament.
it('keeps Filament components out of the views every host compiles', function () {
    $using = [];
    foreach (View::getFinder()->getHints()['stats'] as $path) {
        foreach (Finder::create()->files()->in($path)->name('*.blade.php') as $file) {
            if (str_contains($file->getContents(), '<x-filament')) {
                $using[] = $file->getRelativePathname();
            }
        }
    }

    expect($using)->toBe([]);
});

it('registers the Filament views when Filament is installed', function () {
    expect(View::exists('stats-filament::tab-chart'))->toBeTrue();
});

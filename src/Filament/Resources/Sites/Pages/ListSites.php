<?php

namespace N2ns\Stats\Filament\Resources\Sites\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use N2ns\Stats\Filament\Resources\Sites\SiteResource;

class ListSites extends ListRecords
{
    protected static string $resource = SiteResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}

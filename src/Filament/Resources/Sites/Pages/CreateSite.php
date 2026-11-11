<?php

namespace N2ns\Stats\Filament\Resources\Sites\Pages;

use Filament\Resources\Pages\CreateRecord;
use N2ns\Stats\Filament\Resources\Sites\SiteResource;

/** After creating a site, its page shows the embed code. */
class CreateSite extends CreateRecord
{
    protected static string $resource = SiteResource::class;

    protected function getRedirectUrl(): string
    {
        return SiteResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}

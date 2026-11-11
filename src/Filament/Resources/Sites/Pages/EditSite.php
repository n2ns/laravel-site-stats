<?php

namespace N2ns\Stats\Filament\Resources\Sites\Pages;

use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use N2ns\Stats\Filament\Resources\Sites\SiteResource;

class EditSite extends EditRecord
{
    protected static string $resource = SiteResource::class;

    protected function getHeaderActions(): array
    {
        return [ViewAction::make(), SiteResource::deleteAction()];
    }
}

<?php

namespace N2ns\Stats\Filament\Resources\Sites\Pages;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use N2ns\Stats\Filament\Resources\Sites\SiteResource;
use N2ns\Stats\Models\Site;

/** A site with its embed code; the server key can be generated or rotated here. */
class ViewSite extends ViewRecord
{
    protected static string $resource = SiteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SiteResource::statisticsAction()->record($this->getRecord()),
            $this->rotateKeyAction(),
            EditAction::make(),
            SiteResource::deleteAction(),
        ];
    }

    private function rotateKeyAction(): Action
    {
        return Action::make('rotateKey')
            ->label(fn (Site $record): string => (string) __($record->server_key_hash === null ? 'stats::filament.sites.generate_key' : 'stats::filament.sites.rotate_key'))
            ->icon(Heroicon::OutlinedKey)
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription(fn (Site $record): string => (string) __($record->server_key_hash === null ? 'stats::filament.sites.generate_confirm' : 'stats::filament.sites.rotate_confirm'))
            ->authorize(fn (): bool => SiteResource::canEdit($this->getRecord()))
            ->action(function (Site $record): void {
                $this->replaceMountedAction('serverKey', ['env' => SiteResource::rotateServerKey($record)]);
            });
    }

    /** Shows the new key once, as the .env lines of the client app. */
    public function serverKeyAction(): Action
    {
        return Action::make('serverKey')
            ->modalHeading(__('stats::filament.sites.new_key_title'))
            ->modalDescription(__('stats::filament.sites.new_key'))
            ->schema(fn (array $arguments): array => [
                TextEntry::make('env')
                    ->hiddenLabel()
                    ->state(explode("\n", (string) ($arguments['env'] ?? '')))
                    ->fontFamily(FontFamily::Mono)
                    ->listWithLineBreaks()
                    ->copyable()
                    ->copyableState((string) ($arguments['env'] ?? ''))
                    ->copyMessage(__('stats::filament.sites.copied')),
            ])
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('stats::filament.sites.close'));
    }
}

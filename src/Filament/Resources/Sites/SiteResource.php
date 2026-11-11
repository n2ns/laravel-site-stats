<?php

namespace N2ns\Stats\Filament\Resources\Sites;

use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use N2ns\Stats\Console\SiteCommand;
use N2ns\Stats\Filament\Pages\StatsDashboard;
use N2ns\Stats\Filament\StatsPlugin;
use N2ns\Stats\Models\Site;
use UnitEnum;

/**
 * The tracked sites. Fields and validation match the standalone site manager (SiteController); deleting a site and
 * rotating its server key do exactly what `stats:site delete` and `stats:site key` do. Every action needs the
 * viewStats gate.
 */
class SiteResource extends Resource
{
    protected static ?string $model = Site::class;

    protected static ?string $slug = 'stats-sites';

    protected static ?string $recordRouteKeyName = 'public_id';

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static bool $isGloballySearchable = false;

    public static function getAuthorizationResponse(string|UnitEnum $action, ?Model $record = null): Response
    {
        return Gate::allows('viewStats') ? Response::allow() : Response::deny();
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return StatsPlugin::get()->getNavigationGroup();
    }

    public static function getModelLabel(): string
    {
        return (string) __('stats::filament.sites.model');
    }

    public static function getPluralModelLabel(): string
    {
        return (string) __('stats::filament.sites.title');
    }

    public static function getNavigationLabel(): string
    {
        return (string) __('stats::filament.sites.title');
    }

    public static function form(Schema $schema): Schema
    {
        $message = fn (string $key, array $replace = []): string => (string) __('stats::filament.validation.'.$key, $replace);

        return $schema->columns(1)->components([
            TextInput::make('name')
                ->label(__('stats::filament.sites.fields.name'))
                ->required()
                ->maxLength(120)
                ->validationMessages(['required' => $message('required', ['attribute' => __('stats::filament.sites.attributes.name')]), 'max' => $message('max', ['attribute' => __('stats::filament.sites.attributes.name'), 'max' => 120])])
                ->dehydrateStateUsing(fn (?string $state): string => trim((string) $state)),
            TagsInput::make('domains')
                ->label(__('stats::filament.sites.fields.domains'))
                ->helperText(__('stats::filament.sites.help.domains'))
                ->required()
                ->validationMessages(['required' => $message('required', ['attribute' => __('stats::filament.sites.attributes.domains')])])
                ->rules([fn (): Closure => function (string $attribute, mixed $value, Closure $fail) use ($message): void {
                    $domains = self::lines($value);
                    if (self::length($value) > 10000) {
                        $fail($message('max', ['attribute' => __('stats::filament.sites.attributes.domains'), 'max' => 10000]));
                    }
                    if ($domains === []) {
                        $fail($message('domains_empty'));
                    } elseif (count($domains) > Site::MAX_DOMAINS) {
                        $fail($message('domains_max', ['max' => Site::MAX_DOMAINS]));
                    }
                    foreach ($domains as $domain) {
                        if (! Site::isValidDomain($domain)) {
                            $fail($message('domain_invalid', ['value' => $domain]));
                        }
                    }
                }])
                ->dehydrateStateUsing(fn (mixed $state): array => self::lines($state)),
            Select::make('timezone')
                ->label(__('stats::filament.sites.fields.timezone'))
                ->helperText(__('stats::filament.sites.help.timezone'))
                ->options(fn (): array => array_combine(timezone_identifiers_list(), timezone_identifiers_list()))
                ->searchable()
                ->required()
                ->default('UTC')
                ->rules(['timezone:all'])
                ->validationMessages(['required' => $message('required', ['attribute' => __('stats::filament.sites.attributes.timezone')]), 'timezone' => $message('timezone')]),
            TagsInput::make('locales')
                ->label(__('stats::filament.sites.fields.locales'))
                ->helperText(__('stats::filament.sites.help.locales'))
                ->rules([fn (): Closure => function (string $attribute, mixed $value, Closure $fail) use ($message): void {
                    $locales = self::lines($value);
                    if (self::length($value) > 2000) {
                        $fail($message('max', ['attribute' => __('stats::filament.sites.attributes.locales'), 'max' => 2000]));
                    }
                    if (count($locales) > Site::MAX_LOCALES) {
                        $fail($message('locales_max', ['max' => Site::MAX_LOCALES]));
                    }
                    foreach ($locales as $locale) {
                        if (! Site::isValidLocale($locale)) {
                            $fail($message('locale_invalid', ['value' => $locale]));
                        }
                    }
                }])
                ->dehydrateStateUsing(fn (mixed $state): array => self::lines($state)),
            TextInput::make('default_locale')
                ->label(__('stats::filament.sites.fields.default_locale'))
                ->helperText(__('stats::filament.sites.help.default_locale'))
                ->maxLength(16)
                ->validationMessages(['max' => $message('max', ['attribute' => __('stats::filament.sites.attributes.default_locale'), 'max' => 16])])
                ->rules([fn (): Closure => function (string $attribute, mixed $value, Closure $fail) use ($message): void {
                    $locale = strtolower(trim((string) $value));
                    if ($locale !== '' && ! Site::isValidLocale($locale)) {
                        $fail($message('default_locale_invalid', ['value' => $locale]));
                    }
                }])
                ->dehydrateStateUsing(fn (?string $state): ?string => ($locale = strtolower(trim((string) $state))) !== '' ? $locale : null),
            TagsInput::make('exclude_paths')
                ->label(__('stats::filament.sites.fields.exclude_paths'))
                ->helperText(__('stats::filament.sites.help.exclude_paths'))
                ->rules([fn (): Closure => function (string $attribute, mixed $value, Closure $fail) use ($message): void {
                    $paths = self::lines($value, lower: false);
                    if (self::length($value) > 20000) {
                        $fail($message('max', ['attribute' => __('stats::filament.sites.attributes.exclude_paths'), 'max' => 20000]));
                    }
                    if (count($paths) > Site::MAX_EXCLUDE_PATHS) {
                        $fail($message('paths_max', ['max' => Site::MAX_EXCLUDE_PATHS]));
                    }
                    foreach ($paths as $path) {
                        if (! Site::isValidExcludePath($path)) {
                            $fail($message('path_invalid', ['value' => $path, 'max' => Site::MAX_PATH_LENGTH]));
                        }
                    }
                }])
                ->dehydrateStateUsing(fn (mixed $state): array => self::lines($state, lower: false)),
            Toggle::make('public_counts')
                ->label(__('stats::filament.sites.fields.public_counts'))
                ->helperText(__('stats::filament.sites.help.public_counts'))
                ->default(false),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('stats::filament.sites.embed'))
                ->description(__('stats::filament.sites.embed_help'))
                ->schema([
                    TextEntry::make('snippet')
                        ->hiddenLabel()
                        ->state(fn (Site $record): string => SiteCommand::snippet($record))
                        ->fontFamily(FontFamily::Mono)
                        ->copyable()
                        ->copyMessage(__('stats::filament.sites.copied')),
                ]),
            Section::make(__('stats::filament.sites.settings'))->columns(2)->schema([
                TextEntry::make('name')->label(__('stats::filament.sites.fields.name')),
                TextEntry::make('public_id')->label(__('stats::filament.sites.columns.public_id'))->fontFamily(FontFamily::Mono)->copyable(),
                TextEntry::make('domains')->label(__('stats::filament.sites.fields.domains'))->badge(),
                TextEntry::make('timezone')->label(__('stats::filament.sites.fields.timezone')),
                TextEntry::make('locales')->label(__('stats::filament.sites.fields.locales'))->badge()->placeholder('–'),
                TextEntry::make('default_locale')->label(__('stats::filament.sites.fields.default_locale'))->placeholder('–'),
                TextEntry::make('exclude_paths')->label(__('stats::filament.sites.fields.exclude_paths'))->badge()->placeholder('–'),
                IconEntry::make('public_counts')->label(__('stats::filament.sites.fields.public_counts'))->boolean(),
                TextEntry::make('server_key')->label(__('stats::filament.sites.server_key'))
                    ->state(fn (Site $record): string => (string) __($record->server_key_hash === null ? 'stats::filament.sites.key_not_set' : 'stats::filament.sites.key_set')),
                TextEntry::make('created_at')->label(__('stats::filament.sites.columns.created'))->dateTime(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label(__('stats::filament.sites.fields.name'))->searchable()->sortable(),
                TextColumn::make('domains')->label(__('stats::filament.sites.fields.domains'))->badge(),
                TextColumn::make('timezone')->label(__('stats::filament.sites.fields.timezone'))->sortable(),
                TextColumn::make('locales')->label(__('stats::filament.sites.fields.locales'))->badge()->placeholder('–'),
                IconColumn::make('public_counts')->label(__('stats::filament.sites.fields.public_counts'))->boolean(),
                TextColumn::make('created_at')->label(__('stats::filament.sites.columns.created'))->dateTime()->sortable(),
            ])
            ->recordActions([
                self::statisticsAction(),
                ViewAction::make(),
                EditAction::make(),
                self::deleteAction(),
            ])
            ->emptyStateHeading(__('stats::filament.no_sites'));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSites::route('/'),
            'create' => Pages\CreateSite::route('/create'),
            'view' => Pages\ViewSite::route('/{record}'),
            'edit' => Pages\EditSite::route('/{record}/edit'),
        ];
    }

    /** Opens the statistics page for the site. */
    public static function statisticsAction(): Action
    {
        return Action::make('statistics')
            ->label(__('stats::filament.sites.open_statistics'))
            ->icon(Heroicon::OutlinedChartBar)
            ->color('gray')
            ->url(fn (Site $record): string => StatsDashboard::urlFor($record->public_id))
            ->visible(fn (): bool => StatsDashboard::canAccess());
    }

    /** Deletes the site and all of its statistics, as `stats:site delete` does. */
    public static function deleteAction(): DeleteAction
    {
        return DeleteAction::make()
            ->modalDescription(fn (Site $record): string => (string) __('stats::filament.sites.delete_confirm', ['name' => $record->name]))
            ->using(function (Site $record): bool {
                $record->deleteWithStatistics();

                return true;
            });
    }

    /**
     * Generates a new server key as `stats:site key` does (only its hash is stored) and returns the .env lines of the
     * client app, the only place the key is shown.
     */
    public static function rotateServerKey(Site $site): string
    {
        $key = $site->rotateServerKey();

        return implode("\n", [
            'STATS_MODE=client',
            'STATS_CLIENT_ENDPOINT='.SiteCommand::collectorBaseUrl(),
            'STATS_CLIENT_SITE='.$site->public_id,
            'STATS_CLIENT_KEY='.$key,
        ]);
    }

    /**
     * Entries as the standalone site manager reads its one-per-line fields: trimmed, lower-cased unless $lower is false,
     * without blanks or duplicates.
     *
     * @return list<string>
     */
    private static function lines(mixed $state, bool $lower = true): array
    {
        $lines = [];
        foreach (is_array($state) ? $state : [] as $line) {
            $line = trim(is_scalar($line) ? (string) $line : '');
            $lines[] = $lower ? strtolower($line) : $line;
        }

        return array_values(array_unique(array_filter($lines, fn (string $line): bool => $line !== '')));
    }

    /** Characters of the entries written one per line, the length limit of the standalone form's text fields. */
    private static function length(mixed $state): int
    {
        return is_array($state) ? mb_strlen(implode("\n", array_map(fn (mixed $line): string => is_scalar($line) ? (string) $line : '', $state))) : 0;
    }
}

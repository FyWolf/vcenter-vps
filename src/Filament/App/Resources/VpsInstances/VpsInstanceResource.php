<?php

namespace Fywolf\VcenterVps\Filament\App\Resources\VpsInstances;

use BackedEnum;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Table;
use Fywolf\VcenterVps\Filament\App\Resources\VpsInstances\Pages\BootVps;
use Fywolf\VcenterVps\Filament\App\Resources\VpsInstances\Pages\ListVps;
use Fywolf\VcenterVps\Filament\App\Resources\VpsInstances\Pages\SettingsVps;
use Fywolf\VcenterVps\Filament\App\Resources\VpsInstances\Pages\ViewVps;
use Fywolf\VcenterVps\Models\VpsInstance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class VpsInstanceResource extends Resource
{
    protected static ?string $model = VpsInstance::class;

    protected static string|BackedEnum|null $navigationIcon = 'tabler-server';

    protected static ?string $navigationLabel = 'My VPS';

    protected static ?string $modelLabel = 'VPS';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    public static function canViewAny(): bool
    {
        return auth()->check();
    }

    public static function canView(Model $record): bool
    {
        return auth()->check();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::getEloquentQuery()->exists();
    }

    /**
     * Scoped entirely on local columns — see VpsInstance::scopeOwnedBy(). There
     * is nothing to eager-load any more: the pack name and order status the card
     * renders are columns on the instance itself, copied there by billing.
     */
    public static function getEloquentQuery(): Builder
    {
        if (! auth()->check()) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }

        return parent::getEloquentQuery()->ownedBy((int) auth()->id());
    }

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
            ViewVps::class,
            BootVps::class,
            SettingsVps::class,
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ViewColumn::make('vps_card')
                    ->view('vcenter-vps::columns.vps-card')
                    ->searchable(['name']),
            ])
            ->recordUrl(fn (VpsInstance $record) => ViewVps::getUrl(['record' => $record]))
            ->contentGrid(['default' => 1, 'md' => 2])
            ->paginated([10, 20, 50])
            ->defaultPaginationPageOption(10)
            ->emptyStateIcon('tabler-server')
            ->emptyStateHeading('No VPS instances')
            ->emptyStateDescription('You don\'t have any active VPS instances yet.');
    }

    public static function getPages(): array
    {
        return [
            'index'    => ListVps::route('/'),
            'view'     => ViewVps::route('/{record}'),
            'boot'     => BootVps::route('/{record}/boot'),
            'settings' => SettingsVps::route('/{record}/settings'),
        ];
    }
}

<?php

namespace Fywolf\VcenterVps\Filament\App\Resources\VpsInstances;

use App\Enums\TablerIcon;
use BackedEnum;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Fywolf\VcenterVps\Filament\App\Resources\VpsInstances\Pages\BootVps;
use Fywolf\VcenterVps\Filament\App\Resources\VpsInstances\Pages\ListVps;
use Fywolf\VcenterVps\Filament\App\Resources\VpsInstances\Pages\SettingsVps;
use Fywolf\VcenterVps\Filament\App\Resources\VpsInstances\Pages\ViewVps;
use Fywolf\VcenterVps\Models\VpsInstance;
use Fywolf\VcenterVps\Models\VpsInstanceUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The customer's VPS list, in the panel's `app` panel beside their game servers.
 *
 * Shaped after the panel's own `App\Filament\App\Resources\Servers\ServerResource`:
 * the resource stays thin and the table lives on {@see ListVps}, which is where
 * the panel puts it. That is not just tidiness — it is what lets the list honour
 * the same grid/table dashboard preference and reuse the panel's own columns.
 */
class VpsInstanceResource extends Resource
{
    protected static ?string $model = VpsInstance::class;

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::Server;

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

    /**
     * Ownership is checked on the record itself, not merely "is signed in".
     * `getEloquentQuery()` already scopes the list, but a record resolved by id
     * on a detail page has to be checked on its own.
     */
    public static function canView(Model $record): bool
    {
        if (! $record instanceof VpsInstance) {
            return false;
        }

        // Owner *or* somebody it was shared with. Seeing the machine is the
        // baseline; what they can actually do with it is checked per action, and
        // the destructive pages (BootVps, SettingsVps) check ownership on their
        // own rather than trusting this.
        return $record->isOwnedBy(auth()->id())
            || $record->collaborators->contains('user_id', auth()->id());
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::getEloquentQuery()->exists();
    }

    /**
     * Scoped entirely on local columns — see VpsInstance::scopeOwnedBy(). There
     * is nothing to eager-load: the pack name, order status and purchased spec
     * are columns on the instance, copied there by billing.
     */
    public static function getEloquentQuery(): Builder
    {
        if (! auth()->check()) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }

        // `accessibleBy`, not `ownedBy`: a shared machine has to appear in the
        // list, or a collaborator has access they can never reach.
        return parent::getEloquentQuery()
            ->accessibleBy((int) auth()->id())
            ->with('collaborators');
    }

    /**
     * Only the tabs this person can actually open.
     *
     * Both `BootVps` and `SettingsVps` now refuse a collaborator who lacks the
     * grant, so listing them unconditionally would show tabs that 403 — which
     * reads as the panel being broken rather than as a permission they were
     * never given.
     */
    public static function getRecordSubNavigation(Page $page): array
    {
        $record = $page->getRecord();
        $userId = auth()->id();

        $pages = [ViewVps::class];

        if ($record instanceof VpsInstance && $record->isOwnedBy($userId)) {
            // Reinstalling and changing boot media are not grantable at all.
            $pages[] = BootVps::class;
        }

        if ($record instanceof VpsInstance && $record->userCan($userId, VpsInstanceUser::SETTINGS)) {
            $pages[] = SettingsVps::class;
        }

        return $page->generateNavigationItems($pages);
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

<?php

namespace Fywolf\VcenterVps\Filament\Vps\Pages;

use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Fywolf\VcenterVps\Filament\Vps\Concerns\HasVpsContext;
use Illuminate\Support\Facades\Route;

class VpsSettings extends Page
{
    use HasVpsContext;

    protected static ?int $navigationSort = 3;
    protected static string|BackedEnum|null $navigationIcon = 'tabler-settings';
    protected string $view = 'vcenter-vps::vps-settings';

    public string $name = '';

    public static function routes(Panel $panel): void
    {
        Route::get('/{vpsId}/settings', static::class)
            ->middleware(static::getRouteMiddleware($panel))
            ->withoutMiddleware(static::getWithoutRouteMiddleware($panel))
            ->name('vps-settings');
    }

    public static function getNavigationLabel(): string
    {
        return 'Settings';
    }

    public function getTitle(): string
    {
        return 'Settings';
    }

    public function mount(int $vpsId): void
    {
        $this->loadInstance($vpsId);
        $this->name = $this->instance->name ?? '';
    }

    public function saveName(): void
    {
        $this->validate(['name' => 'required|string|max:191']);
        $this->instance->update(['name' => $this->name]);
        $this->instance->refresh();
        Notification::make()->title('Name updated')->success()->send();
    }
}

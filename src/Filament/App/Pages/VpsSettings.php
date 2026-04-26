<?php

namespace Fywolf\VcenterVps\Filament\App\Pages;

use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Fywolf\VcenterVps\Filament\App\Concerns\HasVpsNavigation;
use Illuminate\Support\Facades\Route;

class VpsSettings extends Page
{
    use HasVpsNavigation;

    protected string $view = 'vcenter-vps::vps-settings';

    public string $name = '';

    public static function routes(Panel $panel): void
    {
        Route::get('/vps/{vpsId}/settings', static::class)
            ->middleware(static::getRouteMiddleware($panel))
            ->withoutMiddleware(static::getWithoutRouteMiddleware($panel))
            ->name('vps-settings');
    }

    public function mount(int $vpsId): void
    {
        $this->mountVps($vpsId);
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

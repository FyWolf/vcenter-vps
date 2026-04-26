<?php

namespace Fywolf\VcenterVps\Filament\App\Pages;

use Exception;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Notifications\Notification;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Components\Concerns\HasHeaderActions;
use Fywolf\VcenterVps\Filament\App\Concerns\HasVpsNavigation;
use Fywolf\VcenterVps\Services\VCenterService;
use Illuminate\Support\Facades\Route;

class VpsConsole extends Page
{
    use HasVpsNavigation;
    use InteractsWithActions, HasHeaderActions;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    protected string $view = 'vcenter-vps::vps-console';

    public static function routes(Panel $panel): void
    {
        Route::get('/vps/{vpsId}', static::class)
            ->middleware(static::getRouteMiddleware($panel))
            ->withoutMiddleware(static::getWithoutRouteMiddleware($panel))
            ->name('vps-console');
    }

    public function mount(int $vpsId): void
    {
        $this->mountVps($vpsId);
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('power_on')
                    ->label('Start')
                    ->color('success')
                    ->icon('tabler-player-play')
                    ->visible(fn () => !$this->instance->isRunning())
                    ->requiresConfirmation()
                    ->action(fn () => $this->powerOn()),
                Action::make('power_off')
                    ->label('Stop')
                    ->color('danger')
                    ->icon('tabler-player-stop')
                    ->visible(fn () => $this->instance->isRunning())
                    ->requiresConfirmation()
                    ->action(fn () => $this->powerOff()),
                Action::make('reboot')
                    ->label('Restart')
                    ->color('warning')
                    ->icon('tabler-refresh-alert')
                    ->visible(fn () => $this->instance->isRunning())
                    ->requiresConfirmation()
                    ->action(fn () => $this->reboot()),
            ])->buttonGroup(),
            Action::make('console')
                ->label('Console')
                ->icon('tabler-terminal')
                ->color('primary')
                ->visible(fn () => $this->instance->isRunning())
                ->url(fn () => route('vcenter-vps.console', $this->instance->id))
                ->openUrlInNewTab(),
        ];
    }

    public function powerOn(): void
    {
        try {
            app(VCenterService::class)->powerOn($this->instance->vm_id);
            $this->instance->update(['state_cache' => 'POWERED_ON', 'state_checked_at' => now('UTC')]);
            $this->instance->refresh();
            Notification::make()->title('VPS started')->success()->send();
        } catch (Exception $e) {
            Notification::make()->title('Failed to start VPS')->body($e->getMessage())->danger()->send();
        }
    }

    public function powerOff(): void
    {
        try {
            app(VCenterService::class)->powerOff($this->instance->vm_id);
            $this->instance->update(['state_cache' => 'POWERED_OFF', 'state_checked_at' => now('UTC')]);
            $this->instance->refresh();
            Notification::make()->title('VPS stopped')->success()->send();
        } catch (Exception $e) {
            Notification::make()->title('Failed to stop VPS')->body($e->getMessage())->danger()->send();
        }
    }

    public function reboot(): void
    {
        try {
            app(VCenterService::class)->reboot($this->instance->vm_id);
            Notification::make()->title('VPS restarting...')->success()->send();
        } catch (Exception $e) {
            Notification::make()->title('Failed to restart VPS')->body($e->getMessage())->danger()->send();
        }
    }
}

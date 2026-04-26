<?php

namespace Fywolf\VcenterVps\Filament\Vps\Pages;

use BackedEnum;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Components\Concerns\HasHeaderActions;
use Fywolf\VcenterVps\Filament\Vps\Concerns\HasVpsContext;
use Fywolf\VcenterVps\Services\VCenterService;
use Illuminate\Support\Facades\Route;

class VpsConsole extends Page
{
    use HasVpsContext;
    use InteractsWithActions, HasHeaderActions;

    protected static ?int $navigationSort = 1;
    protected static string|BackedEnum|null $navigationIcon = 'tabler-server';
    protected string $view = 'vcenter-vps::vps-console';

    public static function routes(Panel $panel): void
    {
        Route::get('/{vpsId}', static::class)
            ->middleware(static::getRouteMiddleware($panel))
            ->withoutMiddleware(static::getWithoutRouteMiddleware($panel))
            ->name('vps-console');
    }

    public static function getNavigationLabel(): string
    {
        return 'Overview';
    }

    public function getTitle(): string
    {
        return $this->instance?->name ?? ($this->instance?->order?->packPrice?->pack?->name ?? 'VPS');
    }

    public function mount(int $vpsId): void
    {
        $this->loadInstance($vpsId);
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

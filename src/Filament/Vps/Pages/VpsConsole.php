<?php

namespace Fywolf\VcenterVps\Filament\Vps\Pages;

use BackedEnum;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Concerns\HasHeaderActions;
use Fywolf\VcenterVps\Models\VpsInstance;
use Fywolf\VcenterVps\Services\VCenterService;

class VpsConsole extends Page
{
    use InteractsWithActions, HasHeaderActions;

    protected static ?int $navigationSort = 1;
    protected static string|BackedEnum|null $navigationIcon = 'tabler-server';
    protected string $view = 'vcenter-vps::vps-console';

    public VpsInstance $instance;

    public static function getNavigationLabel(): string
    {
        return 'Overview';
    }

    public function getTitle(): string
    {
        return $this->instance?->name ?? ($this->instance?->order?->packPrice?->pack?->name ?? 'VPS');
    }

    public function mount(): void
    {
        $this->instance = VpsInstance::with(['order.packPrice.pack'])
            ->findOrFail(Filament::getTenant()->id);
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

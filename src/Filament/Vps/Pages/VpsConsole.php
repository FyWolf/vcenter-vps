<?php

namespace Fywolf\VcenterVps\Filament\Vps\Pages;

use BackedEnum;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Components\Concerns\HasHeaderActions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Fywolf\VcenterVps\Filament\Vps\Concerns\HasVpsContext;
use Fywolf\VcenterVps\Services\VCenterService;
use Illuminate\Support\Facades\Route;

class VpsConsole extends Page implements HasForms
{
    use HasVpsContext;
    use InteractsWithActions, HasHeaderActions;
    use InteractsWithForms;

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
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('OS Installation in Progress')
                ->columnSpanFull()
                ->visible(fn () => $this->instance->isAwaitingInstall())
                ->description('Open the console to complete the OS installation, then mark it complete on the ISO tab.')
                ->footerActions([
                    Action::make('open_install_console')
                        ->label('Open Console')
                        ->icon('tabler-terminal')
                        ->color('primary')
                        ->url(fn () => route('vcenter-vps.console', $this->instance->id))
                        ->openUrlInNewTab(),
                    Action::make('manage_iso')
                        ->label('Manage ISO')
                        ->icon('tabler-disc')
                        ->color('gray')
                        ->url(fn () => VpsBoot::getUrl(['vpsId' => $this->instance->id], panel: 'vps')),
                ])
                ->schema([]),

            Section::make('Server Information')
                ->columnSpanFull()
                ->columns(3)
                ->schema([
                    TextEntry::make('vm_ip')
                        ->label('IP Address')
                        ->state(fn () => $this->instance->vm_ip ?? '—')
                        ->copyable(),
                    TextEntry::make('status')
                        ->label('Power Status')
                        ->badge()
                        ->state(fn () => match (true) {
                            $this->instance->isAwaitingInstall() => 'Installing',
                            $this->instance->isRunning() => 'Running',
                            $this->instance->isStopped() => 'Stopped',
                            default => 'Unknown',
                        })
                        ->color(fn () => match (true) {
                            $this->instance->isAwaitingInstall() => 'warning',
                            $this->instance->isRunning() => 'success',
                            $this->instance->isStopped() => 'danger',
                            default => 'gray',
                        }),
                    TextEntry::make('order_status')
                        ->label('Order Status')
                        ->badge()
                        ->state(fn () => $this->instance->order->status->getLabel()),
                    TextEntry::make('expires')
                        ->label('Expires')
                        ->state(fn () => $this->instance->order->expires_at?->diffForHumans() ?? '—')
                        ->visible(fn () => (bool) $this->instance->order->expires_at),
                    TextEntry::make('state_checked_at')
                        ->label('Status checked')
                        ->state(fn () => $this->instance->state_checked_at?->diffForHumans() ?? '—')
                        ->visible(fn () => (bool) $this->instance->state_checked_at),
                ]),

            Section::make('Specifications')
                ->columnSpanFull()
                ->columns(3)
                ->schema([
                    TextEntry::make('cores')
                        ->label('vCPU')
                        ->state(fn () => $this->instance->order->packPrice->cores
                            ? $this->instance->order->packPrice->cores . ' cores'
                            : '—'),
                    TextEntry::make('memory')
                        ->label('RAM')
                        ->state(fn () => ($mem = $this->instance->order->packPrice->memory)
                            ? number_format($mem / 1024, 1) . ' GB'
                            : '—'),
                    TextEntry::make('disk')
                        ->label('Disk')
                        ->state(fn () => $this->instance->order->packPrice->disk
                            ? $this->instance->order->packPrice->disk . ' GB'
                            : '—'),
                ]),
        ]);
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

<?php

namespace Fywolf\VcenterVps\Filament\App\Resources\VpsInstances\Pages;

use BackedEnum;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Fywolf\VcenterVps\Filament\App\Resources\VpsInstances\VpsInstanceResource;
use Fywolf\VcenterVps\Services\VCenterService;

/**
 * @property \Fywolf\VcenterVps\Models\VpsInstance $record
 */
class ViewVps extends Page implements HasForms
{
    use InteractsWithForms;
    use InteractsWithRecord;

    protected static string $resource = VpsInstanceResource::class;

    protected static ?int $navigationSort = 1;
    protected static string|BackedEnum|null $navigationIcon = 'tabler-server';
    protected string $view = 'vcenter-vps::vps-console';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->form->fill();
    }

    public static function getNavigationLabel(): string
    {
        return 'Overview';
    }

    public function getTitle(): string
    {
        return $this->record->getFilamentName();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('OS Installation in Progress')
                ->columnSpanFull()
                ->visible(fn () => $this->record->isAwaitingInstall())
                ->description('Open the console to complete the OS installation, then mark it complete on the ISO tab.')
                ->footerActions([
                    Action::make('open_install_console')
                        ->label('Open Console')
                        ->icon('tabler-terminal')
                        ->color('primary')
                        ->url(fn () => route('vcenter-vps.console', $this->record->id))
                        ->openUrlInNewTab(),
                    Action::make('manage_iso')
                        ->label('Manage ISO')
                        ->icon('tabler-disc')
                        ->color('gray')
                        ->url(fn () => BootVps::getUrl(['record' => $this->record])),
                ])
                ->schema([]),

            Section::make('Server Information')
                ->columnSpanFull()
                ->columns(3)
                ->schema([
                    TextEntry::make('vm_ip')
                        ->label('IP Address')
                        ->state(fn () => $this->record->vm_ip ?? '—')
                        ->copyable(),
                    TextEntry::make('status')
                        ->label('Power Status')
                        ->badge()
                        ->state(fn () => match (true) {
                            $this->record->isAwaitingInstall() => 'Installing',
                            $this->record->isRunning() => 'Running',
                            $this->record->isStopped() => 'Stopped',
                            default => 'Unknown',
                        })
                        ->color(fn () => match (true) {
                            $this->record->isAwaitingInstall() => 'warning',
                            $this->record->isRunning() => 'success',
                            $this->record->isStopped() => 'danger',
                            default => 'gray',
                        }),
                    TextEntry::make('order_status')
                        ->label('Order Status')
                        ->badge()
                        ->state(fn () => $this->record->order->status->getLabel()),
                    TextEntry::make('expires')
                        ->label('Expires')
                        ->state(fn () => $this->record->order->expires_at?->diffForHumans() ?? '—')
                        ->visible(fn () => (bool) $this->record->order->expires_at),
                    TextEntry::make('state_checked_at')
                        ->label('Status checked')
                        ->state(fn () => $this->record->state_checked_at?->diffForHumans() ?? '—')
                        ->visible(fn () => (bool) $this->record->state_checked_at),
                ]),

            Section::make('Specifications')
                ->columnSpanFull()
                ->columns(3)
                ->schema([
                    TextEntry::make('cores')
                        ->label('vCPU')
                        ->state(fn () => $this->record->order->packPrice->cores
                            ? $this->record->order->packPrice->cores . ' cores'
                            : '—'),
                    TextEntry::make('memory')
                        ->label('RAM')
                        ->state(fn () => ($mem = $this->record->order->packPrice->memory)
                            ? number_format($mem / 1024, 1) . ' GB'
                            : '—'),
                    TextEntry::make('disk')
                        ->label('Disk')
                        ->state(fn () => $this->record->order->packPrice->disk
                            ? $this->record->order->packPrice->disk . ' GB'
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
                    ->visible(fn () => !$this->record->isRunning())
                    ->requiresConfirmation()
                    ->action(fn () => $this->powerOn()),
                Action::make('power_off')
                    ->label('Stop')
                    ->color('danger')
                    ->icon('tabler-player-stop')
                    ->visible(fn () => $this->record->isRunning())
                    ->requiresConfirmation()
                    ->action(fn () => $this->powerOff()),
                Action::make('reboot')
                    ->label('Restart')
                    ->color('warning')
                    ->icon('tabler-refresh-alert')
                    ->visible(fn () => $this->record->isRunning())
                    ->requiresConfirmation()
                    ->action(fn () => $this->reboot()),
            ])->buttonGroup(),
            Action::make('console')
                ->label('Console')
                ->icon('tabler-terminal')
                ->color('primary')
                ->visible(fn () => $this->record->isRunning())
                ->url(fn () => route('vcenter-vps.console', $this->record->id))
                ->openUrlInNewTab(),
        ];
    }

    public function powerOn(): void
    {
        try {
            app(VCenterService::class)->powerOn($this->record->vm_id);
            $this->record->update(['state_cache' => 'POWERED_ON', 'state_checked_at' => now('UTC')]);
            $this->record->refresh();
            Notification::make()->title('VPS started')->success()->send();
        } catch (Exception $e) {
            Notification::make()->title('Failed to start VPS')->body($e->getMessage())->danger()->send();
        }
    }

    public function powerOff(): void
    {
        try {
            app(VCenterService::class)->powerOff($this->record->vm_id);
            $this->record->update(['state_cache' => 'POWERED_OFF', 'state_checked_at' => now('UTC')]);
            $this->record->refresh();
            Notification::make()->title('VPS stopped')->success()->send();
        } catch (Exception $e) {
            Notification::make()->title('Failed to stop VPS')->body($e->getMessage())->danger()->send();
        }
    }

    public function reboot(): void
    {
        try {
            app(VCenterService::class)->reboot($this->record->vm_id);
            Notification::make()->title('VPS restarting...')->success()->send();
        } catch (Exception $e) {
            Notification::make()->title('Failed to restart VPS')->body($e->getMessage())->danger()->send();
        }
    }
}

<?php

namespace Fywolf\VcenterVps\Filament\Admin\Resources\VpsInstances;

use Exception;
use Fywolf\Billing\Enums\OrderStatus;
use Fywolf\VcenterVps\Filament\Admin\Resources\VpsInstances\Pages\ListVpsInstances;
use Fywolf\VcenterVps\Models\VpsInstance;
use Fywolf\VcenterVps\Services\VCenterService;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VpsInstanceResource extends Resource
{
    protected static ?string $model = VpsInstance::class;

    protected static string|\BackedEnum|null $navigationIcon = 'tabler-server';

    protected static string|\UnitEnum|null $navigationGroup = 'VCenter VPS';

    protected static ?string $navigationLabel = 'VPS Instances';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                TextColumn::make('vm_id')
                    ->label('VM ID')
                    ->copyable()
                    ->searchable(),
                TextColumn::make('vm_ip')
                    ->label('IP Address')
                    ->placeholder('—')
                    ->copyable(),
                TextColumn::make('order.id')
                    ->label('Order')
                    ->sortable()
                    ->url(fn (VpsInstance $instance) => \Fywolf\Billing\Filament\Admin\Resources\Orders\OrderResource::getUrl('index') . '?tableSearch=' . $instance->order_id),
                TextColumn::make('order.customer')
                    ->label('Customer')
                    ->formatStateUsing(fn ($state) => $state?->getLabel() ?? '—'),
                TextColumn::make('order.packPrice.pack.name')
                    ->label('Pack'),
                TextColumn::make('install_status')
                    ->label('Install')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'pending'  => 'warning',
                        'complete' => 'success',
                        default    => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'pending'  => 'Installing',
                        'complete' => 'Done',
                        default    => '—',
                    })
                    ->placeholder('—'),
                TextColumn::make('state_cache')
                    ->label('State')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'POWERED_ON'  => 'success',
                        'POWERED_OFF' => 'danger',
                        'SUSPENDED'   => 'warning',
                        default       => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'POWERED_ON'  => 'Running',
                        'POWERED_OFF' => 'Stopped',
                        'SUSPENDED'   => 'Suspended',
                        default       => 'Unknown',
                    }),
                TextColumn::make('order.status')
                    ->label('Order Status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('order.expires_at')
                    ->label('Expires')
                    ->since()
                    ->placeholder('—'),
            ])
            ->recordActions([
                Action::make('mark_install_complete')
                    ->label('Mark Install Complete')
                    ->icon('tabler-circle-check')
                    ->color('success')
                    ->visible(fn (VpsInstance $instance) => $instance->install_status === VpsInstance::INSTALL_PENDING)
                    ->requiresConfirmation()
                    ->action(function (VpsInstance $instance) {
                        $instance->update(['install_status' => VpsInstance::INSTALL_COMPLETE]);
                        Notification::make()->title('Installation marked as complete')->success()->send();
                    }),
                Action::make('refresh_state')
                    ->label('Refresh')
                    ->icon('tabler-refresh')
                    ->color('gray')
                    ->action(function (VpsInstance $instance) {
                        try {
                            $state = app(VCenterService::class)->getState($instance->vm_id);
                            $instance->update([
                                'state_cache'      => $state,
                                'state_checked_at' => now('UTC'),
                            ]);
                            Notification::make()->title('State refreshed')->success()->send();
                        } catch (Exception $e) {
                            Notification::make()->title('Failed to refresh state')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Action::make('set_ip')
                    ->label('Set IP')
                    ->icon('tabler-network')
                    ->color('gray')
                    ->form([
                        TextInput::make('vm_ip')
                            ->label('IP Address')
                            ->required()
                            ->default(fn (VpsInstance $instance) => $instance->vm_ip),
                    ])
                    ->action(function (VpsInstance $instance, array $data) {
                        $instance->update(['vm_ip' => $data['vm_ip']]);
                        Notification::make()->title('IP address updated')->success()->send();
                    }),
                Action::make('power_on')
                    ->label('Start')
                    ->icon('tabler-player-play')
                    ->color('success')
                    ->visible(fn (VpsInstance $instance) => $instance->state_cache !== 'POWERED_ON')
                    ->requiresConfirmation()
                    ->action(function (VpsInstance $instance) {
                        try {
                            app(VCenterService::class)->powerOn($instance->vm_id);
                            $instance->update(['state_cache' => 'POWERED_ON', 'state_checked_at' => now('UTC')]);
                            Notification::make()->title('VM started')->success()->send();
                        } catch (Exception $e) {
                            Notification::make()->title('Start failed')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Action::make('power_off')
                    ->label('Stop')
                    ->icon('tabler-player-stop')
                    ->color('danger')
                    ->visible(fn (VpsInstance $instance) => $instance->state_cache === 'POWERED_ON')
                    ->requiresConfirmation()
                    ->action(function (VpsInstance $instance) {
                        try {
                            app(VCenterService::class)->powerOff($instance->vm_id);
                            $instance->update(['state_cache' => 'POWERED_OFF', 'state_checked_at' => now('UTC')]);
                            Notification::make()->title('VM stopped')->success()->send();
                        } catch (Exception $e) {
                            Notification::make()->title('Stop failed')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Action::make('reboot')
                    ->label('Restart')
                    ->icon('tabler-refresh-alert')
                    ->color('warning')
                    ->visible(fn (VpsInstance $instance) => $instance->state_cache === 'POWERED_ON')
                    ->requiresConfirmation()
                    ->action(function (VpsInstance $instance) {
                        try {
                            app(VCenterService::class)->reboot($instance->vm_id);
                            Notification::make()->title('VM restarted')->success()->send();
                        } catch (Exception $e) {
                            Notification::make()->title('Restart failed')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Action::make('console')
                    ->label('Console')
                    ->icon('tabler-terminal')
                    ->color('primary')
                    ->visible(fn (VpsInstance $instance) => $instance->state_cache === 'POWERED_ON')
                    ->url(fn (VpsInstance $instance) => route('vcenter-vps.admin.console', $instance->id))
                    ->openUrlInNewTab(),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVpsInstances::route('/'),
        ];
    }
}

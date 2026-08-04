<?php

namespace Fywolf\VcenterVps\Filament\App\Resources\VpsInstances\Pages;

use App\Enums\CustomizationKey;
use App\Enums\TablerIcon;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\IconSize;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Table;
use Fywolf\VcenterVps\Filament\App\Resources\VpsInstances\VpsInstanceResource;
use Fywolf\VcenterVps\Models\VpsInstance;
use Fywolf\VcenterVps\Models\VpsInstanceUser;
use Fywolf\VcenterVps\Services\VCenterService;
use Livewire\Attributes\On;

/**
 * The customer's VPS list, built to sit beside the panel's own server list
 * without looking like a bolted-on plugin.
 *
 * Deliberately mirrors `App\Filament\App\Resources\Servers\Pages\ListServers`:
 * the same grid/table split driven by the user's dashboard preference, the same
 * `ActionGroup` of power controls in the same place with the same icons, the
 * same copyable address badge, the same record click-through.
 *
 * **What is not mirrored, and why.** `ListServers` renders three
 * `ProgressBarColumn`s of live CPU, memory and disk *usage*, read per row from
 * the daemon on that server's node. Nothing equivalent exists here: a VPS has no
 * daemon, and `VCenterService` exposes power state but no guest telemetry. A
 * progress bar of allocation against allocation would read 100% forever, so the
 * purchased spec is shown as plain values instead. Adding real bars means a
 * vCenter stats call per row per poll, which is a different conversation.
 */
class ListVps extends ListRecords
{
    protected static string $resource = VpsInstanceResource::class;

    /** @return Stack[] */
    protected function gridColumns(): array
    {
        return [
            Stack::make([
                ViewColumn::make('vps_card')
                    ->view('vcenter-vps::columns.vps-card')
                    ->searchable(['name', 'pack_name']),
            ]),
        ];
    }

    /** @return Column[] */
    protected function tableColumns(): array
    {
        return [
            TextColumn::make('state_cache')
                ->label('Status')
                ->badge()
                ->icon(fn (VpsInstance $instance) => match (true) {
                    $instance->isAwaitingInstall() => TablerIcon::ClockHour4,
                    $instance->isRunning() => TablerIcon::CircleCheck,
                    $instance->isStopped() => TablerIcon::CircleX,
                    default => TablerIcon::QuestionMark,
                })
                ->color(fn (VpsInstance $instance) => match (true) {
                    $instance->isAwaitingInstall() => 'warning',
                    $instance->isRunning() => 'success',
                    $instance->isStopped() => 'danger',
                    default => 'gray',
                })
                ->state(fn (VpsInstance $instance) => match (true) {
                    $instance->isAwaitingInstall() => 'Installing',
                    $instance->isRunning() => 'Running',
                    $instance->isStopped() => 'Stopped',
                    default => 'Unknown',
                })
                ->tooltip(fn (VpsInstance $instance) => $instance->state_checked_at
                    ? 'Checked ' . $instance->state_checked_at->diffForHumans()
                    : null),

            TextColumn::make('name')
                ->label('Name')
                ->state(fn (VpsInstance $instance) => $instance->getFilamentName())
                ->description(fn (VpsInstance $instance) => $instance->pack_name)
                ->grow()
                ->searchable()
                ->sortable(),

            TextColumn::make('vm_ip')
                ->label('')
                ->badge()
                ->visibleFrom('md')
                ->copyable()
                ->state(fn (VpsInstance $instance) => $instance->vm_ip ?? 'None'),

            TextColumn::make('spec_cores')
                ->label('vCPU')
                ->visibleFrom('lg')
                ->state(fn (VpsInstance $instance) => $instance->spec_cores
                    ? $instance->spec_cores . ' cores'
                    : '—'),

            TextColumn::make('spec_memory_mb')
                ->label('RAM')
                ->visibleFrom('lg')
                ->state(fn (VpsInstance $instance) => $instance->spec_memory_mb
                    ? number_format($instance->spec_memory_mb / 1024, 1) . ' GB'
                    : '—'),

            TextColumn::make('spec_disk_gb')
                ->label('Disk')
                ->visibleFrom('lg')
                ->state(fn (VpsInstance $instance) => $instance->spec_disk_gb
                    ? $instance->spec_disk_gb . ' GB'
                    : '—'),
        ];
    }

    public function table(Table $table): Table
    {
        $usingGrid = auth()->user()?->getCustomization(CustomizationKey::DashboardLayout) === 'grid';

        return $table
            ->paginated($usingGrid ? [10, 20, 30, 40] : [10, 20, 50, 100])
            ->defaultPaginationPageOption($usingGrid ? 10 : 20)
            // Re-renders the cached state. Unlike a game server there is no live
            // feed behind this — the row moves when a power action writes
            // `state_cache`, or when Refresh asks vCenter directly.
            ->poll('30s')
            ->columns($usingGrid ? $this->gridColumns() : $this->tableColumns())
            ->recordUrl(fn (VpsInstance $instance) => ViewVps::getUrl(['record' => $instance]))
            ->recordActions($usingGrid ? [] : [$this->powerActionGroup()])
            ->recordActionsAlignment(Alignment::Center->value)
            ->contentGrid($usingGrid ? ['default' => 1, 'md' => 2] : null)
            ->emptyStateIcon(TablerIcon::Server)
            ->emptyStateHeading('You don\'t have any VPS')
            ->emptyStateDescription('A VPS appears here once its order is provisioned.')
            ->defaultSort('id', 'desc');
    }

    /**
     * The same shape as `ListServers::getPowerActionGroup()` — one grouped
     * control at the end of the row, dispatching to a Livewire listener rather
     * than acting inline, so a slow vCenter call cannot block the table render.
     */
    protected function powerActionGroup(): ActionGroup
    {
        return ActionGroup::make([
            Action::make('start')
                ->label('Start')
                ->color('primary')
                ->icon(TablerIcon::PlayerPlayFilled)
                ->visible(fn (VpsInstance $instance) => !$instance->isRunning()
                    && $instance->userCan(auth()->id(), VpsInstanceUser::POWER))
                ->dispatch('vpsPowerAction', fn (VpsInstance $instance) => [
                    'instance' => $instance, 'action' => 'start',
                ]),

            Action::make('restart')
                ->label('Restart')
                ->color('gray')
                ->icon(TablerIcon::Reload)
                ->visible(fn (VpsInstance $instance) => $instance->isRunning()
                    && $instance->userCan(auth()->id(), VpsInstanceUser::POWER))
                ->requiresConfirmation()
                ->dispatch('vpsPowerAction', fn (VpsInstance $instance) => [
                    'instance' => $instance, 'action' => 'restart',
                ]),

            Action::make('stop')
                ->label('Stop')
                ->color('danger')
                ->icon(TablerIcon::PlayerStopFilled)
                ->visible(fn (VpsInstance $instance) => $instance->isRunning()
                    && $instance->userCan(auth()->id(), VpsInstanceUser::POWER))
                ->requiresConfirmation()
                ->dispatch('vpsPowerAction', fn (VpsInstance $instance) => [
                    'instance' => $instance, 'action' => 'stop',
                ]),

            Action::make('console')
                ->label('Console')
                ->color('primary')
                ->icon(TablerIcon::Terminal2)
                ->visible(fn (VpsInstance $instance) => $instance->isRunning()
                    && $instance->userCan(auth()->id(), VpsInstanceUser::CONSOLE))
                ->url(fn (VpsInstance $instance) => route('vcenter-vps.console', $instance->id))
                ->openUrlInNewTab(),

            Action::make('refresh')
                ->label('Refresh status')
                ->color('gray')
                ->icon(TablerIcon::Refresh)
                ->dispatch('vpsPowerAction', fn (VpsInstance $instance) => [
                    'instance' => $instance, 'action' => 'refresh',
                ]),
        ])
            ->icon(TablerIcon::Power)
            ->color('primary')
            ->tooltip('Power controls')
            ->iconSize(IconSize::Large);
    }

    /**
     * Ownership is re-checked here rather than trusted from the dispatch: a
     * Livewire event is client-originated, so the instance id in it is user
     * input no matter which row the button was rendered on.
     */
    #[On('vpsPowerAction')]
    public function vpsPowerAction(VpsInstance $instance, string $action): void
    {
        // Re-checked against the *permission*, not merely against access: a
        // collaborator can hold console rights without power rights, and this
        // event is client-originated so the button being hidden proves nothing.
        //
        // `refresh` is exempt because it only re-reads the power state, and
        // somebody with console access needs to know whether the machine is on.
        // Requiring POWER for it would make the console useless to exactly the
        // collaborator it was granted to.
        $required = $action === 'refresh' ? null : VpsInstanceUser::POWER;

        if ($required !== null && ! $instance->userCan(auth()->id(), $required)) {
            abort(403);
        }

        if ($required === null && ! VpsInstanceResource::canView($instance)) {
            abort(403);
        }

        $vcenter = app(VCenterService::class);

        try {
            match ($action) {
                'start' => $this->applyPower(
                    fn () => $vcenter->powerOn($instance->vm_id),
                    $instance,
                    'POWERED_ON',
                    'VPS started',
                ),
                'stop' => $this->applyPower(
                    fn () => $vcenter->powerOff($instance->vm_id),
                    $instance,
                    'POWERED_OFF',
                    'VPS stopped',
                ),
                'restart' => $this->applyPower(
                    fn () => $vcenter->reboot($instance->vm_id),
                    $instance,
                    'POWERED_ON',
                    'VPS restarted',
                ),
                'refresh' => $this->applyPower(
                    fn () => null,
                    $instance,
                    $vcenter->getState($instance->vm_id),
                    'Status refreshed',
                ),
                default => abort(400),
            };
        } catch (Exception $e) {
            Notification::make()
                ->title('vCenter did not accept that')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    private function applyPower(callable $call, VpsInstance $instance, string $state, string $message): void
    {
        $call();

        $instance->update([
            'state_cache'      => $state,
            'state_checked_at' => now('UTC'),
        ]);

        Notification::make()->title($message)->success()->send();
    }
}

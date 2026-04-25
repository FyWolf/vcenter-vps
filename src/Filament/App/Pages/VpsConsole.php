<?php

namespace Fywolf\VcenterVps\Filament\App\Pages;

use Exception;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Components\Concerns\HasHeaderActions;
use Fywolf\Billing\Enums\OrderStatus;
use Fywolf\Billing\Models\Customer;
use Fywolf\VcenterVps\Jobs\UploadIsoJob;
use Fywolf\VcenterVps\Models\VpsInstance;
use Fywolf\VcenterVps\Services\VCenterService;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Validate;
use Livewire\WithFileUploads;

class VpsConsole extends Page
{
    use InteractsWithActions, HasHeaderActions;
    use WithFileUploads;

    protected string $view = 'vcenter-vps::vps-console';

    public VpsInstance $instance;

    public ?string $isoUrl = null;

    #[Validate(['isoFile' => 'nullable|file|mimes:iso|max:2097152'])]
    public $isoFile = null;

    public static function routes(Panel $panel): void
    {
        Route::get('/vps/{vpsId}', static::class)
            ->middleware(static::getRouteMiddleware($panel))
            ->withoutMiddleware(static::getWithoutRouteMiddleware($panel))
            ->name('vps-console');
    }

    public function mount(int $vpsId): void
    {
        $customer = Customer::where('user_id', auth()->id())->firstOrFail();

        $this->instance = VpsInstance::whereHas('order', fn ($q) => $q
            ->where('customer_id', $customer->id)
            ->whereIn('status', [OrderStatus::Active, OrderStatus::GracePeriod, OrderStatus::Cancelled])
        )
            ->with(['order.packPrice.pack'])
            ->findOrFail($vpsId);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function getTitle(): string
    {
        return $this->instance->order->packPrice->pack->name ?? 'VPS Console';
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

    public function markInstallComplete(): void
    {
        $this->instance->update(['install_status' => VpsInstance::INSTALL_COMPLETE]);
        $this->instance->refresh();
        Notification::make()->title('Installation marked as complete')->success()->send();
    }

    public function swapIsoFromUrl(): void
    {
        $this->validate(['isoUrl' => 'required|url']);

        $libraryId = config('vcenter-vps.upload_library_id');
        if (!$libraryId) {
            Notification::make()->title('ISO upload not available')->body('No upload library configured.')->warning()->send();
            return;
        }

        UploadIsoJob::dispatch($this->instance->id, $libraryId, $this->isoUrl, 'url');
        $this->isoUrl = null;
        Notification::make()
            ->title('ISO download queued')
            ->body('Your ISO is being downloaded and attached. This may take a few minutes.')
            ->success()
            ->send();
    }

    public function swapIsoFromFile(): void
    {
        $this->validate(['isoFile' => 'required|file|max:2097152']);

        $libraryId = config('vcenter-vps.upload_library_id');
        if (!$libraryId) {
            Notification::make()->title('ISO upload not available')->body('No upload library configured.')->warning()->send();
            return;
        }

        $path = $this->isoFile->store('iso-uploads');
        $this->isoFile = null;
        UploadIsoJob::dispatch($this->instance->id, $libraryId, $path, 'storage');
        Notification::make()
            ->title('ISO upload queued')
            ->body('Your ISO is being uploaded and attached. This may take a few minutes.')
            ->success()
            ->send();
    }
}

<?php

namespace Fywolf\VcenterVps\Filament\App\Pages;

use Exception;
use Fywolf\Billing\Enums\OrderStatus;
use Fywolf\Billing\Models\Customer;
use Fywolf\VcenterVps\Jobs\UploadIsoJob;
use Fywolf\VcenterVps\Models\VpsInstance;
use Fywolf\VcenterVps\Services\VCenterService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Validate;
use Livewire\WithFileUploads;

class MyVps extends Page
{
    use WithFileUploads;

    protected string $view = 'vcenter-vps::my-vps';

    protected static ?string $slug = 'my-vps';

    protected static ?string $navigationLabel = 'My VPS';

    protected static string|\BackedEnum|null $navigationIcon = 'tabler-server';

    protected static ?int $navigationSort = 5;

    #[Validate(['isoFile' => 'nullable|file|mimes:iso|max:2097152'])]
    public $isoFile = null;

    public array $isoUrls = [];

    public static function shouldRegisterNavigation(): bool
    {
        $customer = Customer::where('user_id', auth()->id())->first();

        if (!$customer) {
            return false;
        }

        return VpsInstance::whereHas('order', fn ($q) => $q->where('customer_id', $customer->id)
            ->whereIn('status', [OrderStatus::Active, OrderStatus::GracePeriod, OrderStatus::Cancelled])
        )->exists();
    }

    public function getInstances(): Collection
    {
        $customer = Customer::where('user_id', auth()->id())->first();

        if (!$customer) {
            return collect();
        }

        return VpsInstance::whereHas('order', fn ($q) => $q->where('customer_id', $customer->id)
            ->whereIn('status', [OrderStatus::Active, OrderStatus::GracePeriod, OrderStatus::Cancelled])
        )
            ->with(['order.packPrice.pack', 'order.customer'])
            ->get();
    }

    public function powerOn(int $instanceId): void
    {
        $instance = $this->resolveInstance($instanceId);

        try {
            app(VCenterService::class)->powerOn($instance->vm_id);
            $instance->update(['state_cache' => 'POWERED_ON', 'state_checked_at' => now('UTC')]);
            Notification::make()->title('VPS started')->success()->send();
        } catch (Exception $e) {
            Notification::make()->title('Failed to start VPS')->body($e->getMessage())->danger()->send();
        }
    }

    public function powerOff(int $instanceId): void
    {
        $instance = $this->resolveInstance($instanceId);

        try {
            app(VCenterService::class)->powerOff($instance->vm_id);
            $instance->update(['state_cache' => 'POWERED_OFF', 'state_checked_at' => now('UTC')]);
            Notification::make()->title('VPS stopped')->success()->send();
        } catch (Exception $e) {
            Notification::make()->title('Failed to stop VPS')->body($e->getMessage())->danger()->send();
        }
    }

    public function reboot(int $instanceId): void
    {
        $instance = $this->resolveInstance($instanceId);

        try {
            app(VCenterService::class)->reboot($instance->vm_id);
            Notification::make()->title('VPS restarting...')->success()->send();
        } catch (Exception $e) {
            Notification::make()->title('Failed to restart VPS')->body($e->getMessage())->danger()->send();
        }
    }

    public function openConsole(int $instanceId): void
    {
        $instance = $this->resolveInstance($instanceId);

        try {
            $url = app(VCenterService::class)->getConsoleTicket($instance->vm_id);
            $this->dispatch('open-console', url: $url);
        } catch (Exception $e) {
            Notification::make()->title('Failed to open console')->body($e->getMessage())->danger()->send();
        }
    }

    public function markInstallComplete(int $instanceId): void
    {
        $instance = $this->resolveInstance($instanceId);

        $instance->update(['install_status' => VpsInstance::INSTALL_COMPLETE]);

        Notification::make()->title('Installation marked as complete')->success()->send();
    }

    public function swapIsoFromUrl(int $instanceId): void
    {
        $this->validate(['isoUrls.' . $instanceId => 'required|url']);

        $instance  = $this->resolveInstance($instanceId);
        $libraryId = config('vcenter-vps.upload_library_id');

        if (!$libraryId) {
            Notification::make()
                ->title('ISO upload not available')
                ->body('No upload library configured. Contact your administrator.')
                ->warning()
                ->send();
            return;
        }

        UploadIsoJob::dispatch(
            $instance->id,
            $libraryId,
            $this->isoUrls[$instanceId],
            'url'
        );

        unset($this->isoUrls[$instanceId]);

        Notification::make()
            ->title('ISO download queued')
            ->body('Your ISO is being downloaded and attached. This may take a few minutes.')
            ->success()
            ->send();
    }

    public function swapIsoFromFile(int $instanceId): void
    {
        $this->validate(['isoFile' => 'required|file|max:2097152']);

        $instance  = $this->resolveInstance($instanceId);
        $libraryId = config('vcenter-vps.upload_library_id');

        if (!$libraryId) {
            Notification::make()
                ->title('ISO upload not available')
                ->body('No upload library configured. Contact your administrator.')
                ->warning()
                ->send();
            return;
        }

        $path = $this->isoFile->store('iso-uploads');
        $this->isoFile = null;

        UploadIsoJob::dispatch($instance->id, $libraryId, $path, 'storage');

        Notification::make()
            ->title('ISO upload queued')
            ->body('Your ISO is being uploaded and attached. This may take a few minutes.')
            ->success()
            ->send();
    }

    private function resolveInstance(int $instanceId): VpsInstance
    {
        $customer = Customer::where('user_id', auth()->id())->firstOrFail();

        return VpsInstance::whereHas('order', fn ($q) => $q->where('customer_id', $customer->id))
            ->findOrFail($instanceId);
    }
}

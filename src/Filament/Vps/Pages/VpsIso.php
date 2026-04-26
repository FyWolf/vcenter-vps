<?php

namespace Fywolf\VcenterVps\Filament\Vps\Pages;

use BackedEnum;
use Exception;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Fywolf\VcenterVps\Filament\Vps\Concerns\HasVpsContext;
use Fywolf\VcenterVps\Jobs\UploadIsoJob;
use Fywolf\VcenterVps\Models\VpsInstance;
use Fywolf\VcenterVps\Services\VCenterService;
use Illuminate\Support\Facades\Route;
use Livewire\WithFileUploads;

class VpsIso extends Page
{
    use HasVpsContext;
    use WithFileUploads;

    protected static ?int $navigationSort = 2;
    protected static string|BackedEnum|null $navigationIcon = 'tabler-disc';
    protected string $view = 'vcenter-vps::vps-iso';

    public ?string $isoUrl = null;
    public $isoFile = null;
    public ?string $selectedLibraryItemId = null;
    public array $availableIsos = [];

    public static function routes(Panel $panel): void
    {
        Route::get('/{vpsId}/iso', static::class)
            ->middleware(static::getRouteMiddleware($panel))
            ->withoutMiddleware(static::getWithoutRouteMiddleware($panel))
            ->name('vps-iso');
    }

    public static function getNavigationLabel(): string
    {
        return 'ISO';
    }

    public function getTitle(): string
    {
        return 'ISO';
    }

    public function mount(int $vpsId): void
    {
        $this->loadInstance($vpsId);
        $this->loadAvailableIsos();
    }

    public function loadAvailableIsos(): void
    {
        $libraryId = config('vcenter-vps.upload_library_id');
        if (!$libraryId) {
            return;
        }

        try {
            $this->availableIsos = app(VCenterService::class)->listContentLibraryItems($libraryId);
        } catch (Exception) {
            $this->availableIsos = [];
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
            Notification::make()->title('ISO upload not available')->body('No upload library configured. Contact your administrator.')->warning()->send();
            return;
        }

        UploadIsoJob::dispatch($this->instance->id, $libraryId, $this->isoUrl, 'url');
        $this->isoUrl = null;
        Notification::make()
            ->title('ISO download queued')
            ->body('vCenter is downloading the ISO directly. This may take several minutes depending on file size.')
            ->success()
            ->send();
    }

    public function swapIsoFromFile(): void
    {
        $this->validate(['isoFile' => 'required|file|max:2097152']);

        $libraryId = config('vcenter-vps.upload_library_id');
        if (!$libraryId) {
            Notification::make()->title('ISO upload not available')->body('No upload library configured. Contact your administrator.')->warning()->send();
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

    public function attachFromLibrary(): void
    {
        $this->validate(['selectedLibraryItemId' => 'required|string']);

        try {
            $service = app(VCenterService::class);

            if ($this->instance->cdrom_id) {
                $service->swapCdromToLibraryItem(
                    $this->instance->vm_id,
                    $this->instance->cdrom_id,
                    $this->selectedLibraryItemId
                );
            } else {
                $cdromId = $service->addCdromFromLibrary($this->instance->vm_id, $this->selectedLibraryItemId);
                $this->instance->update(['cdrom_id' => $cdromId]);
            }

            $this->instance->update(['iso_item_id' => $this->selectedLibraryItemId]);
            $this->instance->refresh();
            $this->selectedLibraryItemId = null;

            Notification::make()->title('ISO attached')->success()->send();
        } catch (Exception $e) {
            Notification::make()->title('Failed to attach ISO')->body($e->getMessage())->danger()->send();
        }
    }
}

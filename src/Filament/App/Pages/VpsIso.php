<?php

namespace Fywolf\VcenterVps\Filament\App\Pages;

use Filament\Notifications\Notification;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Pages\Page;
use Filament\Panel;
use Fywolf\VcenterVps\Filament\App\Concerns\HasVpsNavigation;
use Fywolf\VcenterVps\Jobs\UploadIsoJob;
use Fywolf\VcenterVps\Models\VpsInstance;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Validate;
use Livewire\WithFileUploads;

class VpsIso extends Page
{
    use HasVpsNavigation;
    use WithFileUploads;

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    protected string $view = 'vcenter-vps::vps-iso';

    public ?string $isoUrl = null;

    #[Validate(['isoFile' => 'nullable|file|mimes:iso|max:2097152'])]
    public $isoFile = null;

    public static function routes(Panel $panel): void
    {
        Route::get('/vps/{vpsId}/iso', static::class)
            ->middleware(static::getRouteMiddleware($panel))
            ->withoutMiddleware(static::getWithoutRouteMiddleware($panel))
            ->name('vps-iso');
    }

    public function mount(int $vpsId): void
    {
        $this->mountVps($vpsId);
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
            ->body('Your ISO is being downloaded and attached. This may take a few minutes.')
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
}

<?php

namespace Fywolf\VcenterVps\Filament\Vps\Pages;

use BackedEnum;
use Exception;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Fywolf\VcenterVps\Filament\Vps\Concerns\HasVpsContext;
use Fywolf\VcenterVps\Models\VpsInstance;
use Fywolf\VcenterVps\Services\VCenterService;
use Illuminate\Support\Facades\Route;

class VpsBoot extends Page implements HasForms
{
    use HasVpsContext;
    use InteractsWithForms;

    protected static ?int $navigationSort = 2;
    protected static string|BackedEnum|null $navigationIcon = 'tabler-disc';
    protected string $view = 'vcenter-vps::vps-boot';

    public ?string $selectedLibraryItemId = null;
    public ?string $bootOrder = 'disk_first';
    public array $availableIsos = [];

    public static function routes(Panel $panel): void
    {
        Route::get('/{vpsId}/boot', static::class)
            ->middleware(static::getRouteMiddleware($panel))
            ->withoutMiddleware(static::getWithoutRouteMiddleware($panel))
            ->name('vps-boot');
    }

    public static function getNavigationLabel(): string
    {
        return 'Boot';
    }

    public function getTitle(): string
    {
        return 'Boot';
    }

    public function mount(int $vpsId): void
    {
        $this->loadInstance($vpsId);
        $this->loadAvailableIsos();
        $this->bootOrder = $this->detectBootOrder();
        $this->form->fill();
    }

    public function loadAvailableIsos(): void
    {
        try {
            $vcenter = app(VCenterService::class);
            $datastoreId = config('vcenter-vps.iso_datastore_id');
            $isos = [];

            foreach ($vcenter->listContentLibrariesWithDatastores() as $library) {
                if ($datastoreId && $library['datastore_id'] !== $datastoreId) {
                    continue;
                }

                foreach ($vcenter->listContentLibraryItems($library['id']) as $item) {
                    $isos[$item['id']] = "[{$library['name']}] {$item['name']}";
                }
            }

            $this->availableIsos = $isos;
        } catch (Exception) {
            $this->availableIsos = [];
        }
    }

    private function detectBootOrder(): string
    {
        try {
            $order = app(VCenterService::class)->getBootOrder($this->instance->vm_id);
            return match ($order[0] ?? null) {
                'CDROM'    => 'cd_first',
                'ETHERNET' => 'network_first',
                default    => 'disk_first',
            };
        } catch (Exception) {
            return 'disk_first';
        }
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Complete OS Installation')
                ->columnSpanFull()
                ->visible(fn () => $this->instance->isAwaitingInstall())
                ->description('Open the console to complete OS installation, then mark it as done.')
                ->footerActions([
                    Action::make('open_install_console')
                        ->label('Open Console')
                        ->icon('tabler-terminal')
                        ->color('primary')
                        ->url(fn () => route('vcenter-vps.console', $this->instance->id))
                        ->openUrlInNewTab(),
                    Action::make('mark_install_complete')
                        ->label('Mark as Done')
                        ->icon('tabler-circle-check')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn () => $this->markInstallComplete()),
                ])
                ->schema([]),

            Section::make('Boot Device')
                ->columnSpanFull()
                ->description('Which device the VPS tries to boot from first. Takes effect on the next power cycle.')
                ->footerActions([
                    Action::make('apply_boot_order')
                        ->label('Apply')
                        ->icon('tabler-circle-check')
                        ->action(fn () => $this->saveBootOrder()),
                ])
                ->schema([
                    Select::make('bootOrder')
                        ->label('Priority')
                        ->options([
                            'disk_first'    => 'Disk → CD → Network (normal operation)',
                            'cd_first'      => 'CD → Disk → Network (boot from attached ISO)',
                            'network_first' => 'Network → Disk → CD (PXE / network boot)',
                        ])
                        ->required()
                        ->selectablePlaceholder(false),
                ]),

            Section::make('Boot ISO')
                ->columnSpanFull()
                ->description('Mount an ISO from the vCenter content library.')
                ->footerActions([
                    Action::make('attach_from_library')
                        ->label('Attach')
                        ->icon('tabler-link')
                        ->color('primary')
                        ->action(fn () => $this->attachFromLibrary()),
                    Action::make('refresh_library')
                        ->label('Refresh list')
                        ->icon('tabler-refresh')
                        ->color('gray')
                        ->action(fn () => $this->loadAvailableIsos()),
                ])
                ->schema([
                    Select::make('selectedLibraryItemId')
                        ->label('ISO')
                        ->options(fn () => $this->availableIsos)
                        ->searchable()
                        ->placeholder(empty($this->availableIsos) ? 'No ISOs available — ask your administrator to configure the ISO datastore' : 'Select an ISO')
                        ->disabled(fn () => empty($this->availableIsos)),
                ]),
        ]);
    }

    public function markInstallComplete(): void
    {
        try {
            app(VCenterService::class)->setBootOrder($this->instance->vm_id, ['DISK', 'CDROM', 'ETHERNET']);
        } catch (Exception) {
            // best-effort
        }

        $this->instance->update(['install_status' => VpsInstance::INSTALL_COMPLETE]);
        $this->instance->refresh();
        $this->bootOrder = 'disk_first';
        Notification::make()->title('Installation marked as complete')->success()->send();
    }

    public function saveBootOrder(): void
    {
        $preset = $this->form->getState()['bootOrder'] ?? 'disk_first';

        $order = match ($preset) {
            'cd_first'      => ['CDROM', 'DISK', 'ETHERNET'],
            'network_first' => ['ETHERNET', 'DISK', 'CDROM'],
            default         => ['DISK', 'CDROM', 'ETHERNET'],
        };

        try {
            app(VCenterService::class)->setBootOrder($this->instance->vm_id, $order);
            Notification::make()->title('Boot order updated')->success()->send();
        } catch (Exception $e) {
            Notification::make()->title('Failed to update boot order')->body($e->getMessage())->danger()->send();
        }
    }

    public function attachFromLibrary(): void
    {
        if (!$this->selectedLibraryItemId) {
            Notification::make()->title('Please select an ISO first')->warning()->send();
            return;
        }

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

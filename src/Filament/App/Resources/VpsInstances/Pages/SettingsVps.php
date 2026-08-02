<?php

namespace Fywolf\VcenterVps\Filament\App\Resources\VpsInstances\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Fywolf\VcenterVps\Filament\App\Resources\VpsInstances\VpsInstanceResource;

/**
 * @property \Fywolf\VcenterVps\Models\VpsInstance $record
 */
class SettingsVps extends Page implements HasForms
{
    use InteractsWithForms;
    use InteractsWithRecord;

    protected static string $resource = VpsInstanceResource::class;

    protected static ?int $navigationSort = 3;
    protected static string|BackedEnum|null $navigationIcon = 'tabler-settings';
    protected string $view = 'vcenter-vps::vps-settings';

    public ?string $name = null;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->name = $this->record->name;
        $this->form->fill();
    }

    public static function getNavigationLabel(): string
    {
        return 'Settings';
    }

    public function getTitle(): string
    {
        return 'Settings';
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Server Information')
                ->columnSpanFull()
                ->footerActions([
                    Action::make('save')
                        ->label('Save')
                        ->icon('tabler-device-floppy')
                        ->action(fn () => $this->save()),
                ])
                ->schema([
                    TextInput::make('name')
                        ->label('Display Name')
                        ->placeholder($this->record->pack_name ?? 'VPS')
                        ->maxLength(191)
                        ->helperText('Used as the display name across the panel. Leave blank to use the pack name.'),
                ]),

            Section::make('Server Details')
                ->columnSpanFull()
                ->columns(3)
                ->schema([
                    TextEntry::make('pack')
                        ->state(fn () => $this->record->pack_name ?? '—'),
                    TextEntry::make('vm_ip')
                        ->label('IP Address')
                        ->state(fn () => $this->record->vm_ip ?? '—')
                        ->copyable(),
                    TextEntry::make('cores')
                        ->label('vCPU')
                        ->state(fn () => $this->record->spec_cores
                            ? $this->record->spec_cores . ' cores'
                            : '—'),
                    TextEntry::make('memory')
                        ->label('RAM')
                        ->state(fn () => ($mem = $this->record->spec_memory_mb)
                            ? number_format($mem / 1024, 1) . ' GB'
                            : '—'),
                    TextEntry::make('disk')
                        ->label('Disk')
                        ->state(fn () => $this->record->spec_disk_gb
                            ? $this->record->spec_disk_gb . ' GB'
                            : '—'),
                    TextEntry::make('expires')
                        ->label('Expires')
                        ->state(fn () => $this->record->order_expires_at?->diffForHumans() ?? '—'),
                ]),
        ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $this->record->update(['name' => $data['name'] ?: null]);
        $this->record->refresh();
        Notification::make()->title('Settings saved')->success()->send();
    }
}

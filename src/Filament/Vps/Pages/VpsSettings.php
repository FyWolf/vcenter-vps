<?php

namespace Fywolf\VcenterVps\Filament\Vps\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Fywolf\VcenterVps\Filament\Vps\Concerns\HasVpsContext;
use Illuminate\Support\Facades\Route;

class VpsSettings extends Page implements HasForms
{
    use HasVpsContext;
    use InteractsWithForms;

    protected static ?int $navigationSort = 3;
    protected static string|BackedEnum|null $navigationIcon = 'tabler-settings';
    protected string $view = 'vcenter-vps::vps-settings';

    public ?string $name = null;

    public static function routes(Panel $panel): void
    {
        Route::get('/{vpsId}/settings', static::class)
            ->middleware(static::getRouteMiddleware($panel))
            ->withoutMiddleware(static::getWithoutRouteMiddleware($panel))
            ->name('vps-settings');
    }

    public static function getNavigationLabel(): string
    {
        return 'Settings';
    }

    public function getTitle(): string
    {
        return 'Settings';
    }

    public function mount(int $vpsId): void
    {
        $this->loadInstance($vpsId);
        $this->name = $this->instance->name;
        $this->form->fill();
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
                        ->placeholder($this->instance->order?->packPrice?->pack?->name ?? 'VPS')
                        ->maxLength(191)
                        ->helperText('Used as the display name across the panel. Leave blank to use the pack name.'),
                ]),

            Section::make('Server Details')
                ->columnSpanFull()
                ->columns(3)
                ->schema([
                    TextEntry::make('pack')
                        ->state(fn () => $this->instance->order?->packPrice?->pack?->name ?? '—'),
                    TextEntry::make('vm_ip')
                        ->label('IP Address')
                        ->state(fn () => $this->instance->vm_ip ?? '—')
                        ->copyable(),
                    TextEntry::make('cores')
                        ->label('vCPU')
                        ->state(fn () => $this->instance->order?->packPrice?->cores
                            ? $this->instance->order->packPrice->cores . ' cores'
                            : '—'),
                    TextEntry::make('memory')
                        ->label('RAM')
                        ->state(fn () => ($mem = $this->instance->order?->packPrice?->memory)
                            ? number_format($mem / 1024, 1) . ' GB'
                            : '—'),
                    TextEntry::make('disk')
                        ->label('Disk')
                        ->state(fn () => $this->instance->order?->packPrice?->disk
                            ? $this->instance->order->packPrice->disk . ' GB'
                            : '—'),
                    TextEntry::make('expires')
                        ->label('Expires')
                        ->state(fn () => $this->instance->order?->expires_at?->diffForHumans() ?? '—'),
                ]),
        ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $this->instance->update(['name' => $data['name'] ?: null]);
        $this->instance->refresh();
        Notification::make()->title('Settings saved')->success()->send();
    }
}

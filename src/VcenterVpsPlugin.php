<?php

namespace Fywolf\VcenterVps;

use App\Contracts\Plugins\HasPluginSettings;
use App\Traits\EnvironmentWriterTrait;
use Exception;
use Filament\Contracts\Plugin;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Panel;
use Filament\Schemas\Components\Fieldset;
use Fywolf\VcenterVps\Services\VCenterService;

class VcenterVpsPlugin implements HasPluginSettings, Plugin
{
    use EnvironmentWriterTrait;

    public function getId(): string
    {
        return 'vcenter-vps';
    }

    public function register(Panel $panel): void
    {
        $id = str($panel->getId())->title();

        $panel->discoverResources(
            plugin_path($this->getId(), "src/Filament/$id/Resources"),
            "Fywolf\\VcenterVps\\Filament\\$id\\Resources"
        );

        $panel->discoverPages(
            plugin_path($this->getId(), "src/Filament/$id/Pages"),
            "Fywolf\\VcenterVps\\Filament\\$id\\Pages"
        );

        $panel->discoverWidgets(
            plugin_path($this->getId(), "src/Filament/$id/Widgets"),
            "Fywolf\\VcenterVps\\Filament\\$id\\Widgets"
        );
    }

    public function boot(Panel $panel): void {}

    public function getSettingsForm(): array
    {
        return [
            Fieldset::make('vCenter Connection')
                ->schema([
                    TextInput::make('vcenter_host')
                        ->label('vCenter Host')
                        ->required()
                        ->default(fn () => config('vcenter-vps.host'))
                        ->placeholder('https://vcenter.example.com'),

                    TextInput::make('vcenter_user')
                        ->label('Username')
                        ->required()
                        ->default(fn () => config('vcenter-vps.user'))
                        ->placeholder('administrator@vsphere.local'),

                    TextInput::make('vcenter_password')
                        ->label('Password')
                        ->password()
                        ->revealable()
                        ->default(fn () => config('vcenter-vps.password')),

                    TextInput::make('vcenter_insecure')
                        ->label('Skip TLS Verification (1 = yes, 0 = no)')
                        ->default(fn () => config('vcenter-vps.insecure') ? '1' : '0')
                        ->helperText('Set to 1 only for self-signed certificates in dev environments.'),
                ]),

            Fieldset::make('ISO Library')
                ->schema([
                    $this->buildIsoDatastoreField(),
                ]),
        ];
    }

    private function buildIsoDatastoreField(): TextInput|Select
    {
        $datastores = [];
        $credentialsConfigured = !empty(config('vcenter-vps.host'))
            && !empty(config('vcenter-vps.user'))
            && !empty(config('vcenter-vps.password'));

        if ($credentialsConfigured) {
            try {
                $datastores = collect(app(VCenterService::class)->listDatastores())
                    ->mapWithKeys(fn ($ds) => [$ds['id'] => $ds['name']])
                    ->all();
            } catch (Exception) {}
        }

        if (empty($datastores)) {
            return TextInput::make('vcenter_iso_datastore_id')
                ->label('ISO Datastore ID')
                ->default(fn () => config('vcenter-vps.iso_datastore_id'))
                ->placeholder('datastore-NN')
                ->helperText('Save vCenter credentials above first to pick from a dropdown of available datastores.');
        }

        return Select::make('vcenter_iso_datastore_id')
            ->label('ISO Datastore')
            ->options($datastores)
            ->default(fn () => config('vcenter-vps.iso_datastore_id'))
            ->searchable()
            ->nullable()
            ->helperText('Customers will only see ISOs from content libraries stored on this datastore. Leave blank to show ISOs from all libraries.');
    }

    public function saveSettings(array $data): void
    {
        $env = [];

        if (!empty($data['vcenter_host']))             $env['VCENTER_HOST']             = $data['vcenter_host'];
        if (!empty($data['vcenter_user']))             $env['VCENTER_USER']             = $data['vcenter_user'];
        if (!empty($data['vcenter_password']))         $env['VCENTER_PASSWORD']         = $data['vcenter_password'];
        if (isset($data['vcenter_insecure']))          $env['VCENTER_INSECURE']         = $data['vcenter_insecure'];
        if (isset($data['vcenter_iso_datastore_id']))  $env['VCENTER_ISO_DATASTORE_ID'] = $data['vcenter_iso_datastore_id'];

        $this->writeToEnvironment($env);

        Notification::make()
            ->title('vCenter settings saved')
            ->success()
            ->send();
    }
}

<?php

namespace Fywolf\VcenterVps\Filament\Admin\Resources\VcenterPackSettings;

use Exception;
use Fywolf\Billing\Models\Pack;
use Fywolf\VcenterVps\Filament\Admin\Resources\VcenterPackSettings\Pages\CreateVcenterPackSetting;
use Fywolf\VcenterVps\Filament\Admin\Resources\VcenterPackSettings\Pages\EditVcenterPackSetting;
use Fywolf\VcenterVps\Filament\Admin\Resources\VcenterPackSettings\Pages\ListVcenterPackSettings;
use Fywolf\VcenterVps\Models\VcenterPackSetting;
use Fywolf\VcenterVps\Services\VCenterService;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VcenterPackSettingResource extends Resource
{
    protected static ?string $model = VcenterPackSetting::class;

    protected static string|\BackedEnum|null $navigationIcon = 'tabler-server-cog';

    protected static string|\UnitEnum|null $navigationGroup = 'VCenter VPS';

    protected static ?string $navigationLabel = 'Pack Settings';

    public static function form(Schema $schema): Schema
    {
        $credentialsConfigured = !empty(config('vcenter-vps.host'))
            && !empty(config('vcenter-vps.user'))
            && !empty(config('vcenter-vps.password'));

        $templates  = [];
        $datastores = [];
        $clusters   = [];

        if ($credentialsConfigured) {
            try {
                $vcenter    = app(VCenterService::class);
                $templates  = self::optionsFrom($vcenter->listTemplates());
                $datastores = self::optionsFrom($vcenter->listDatastores());
                $clusters   = self::optionsFrom($vcenter->listClusters());
            } catch (Exception) {}
        }

        $components = [
            Select::make('pack_id')
                ->label('Billing Pack')
                ->options(Pack::all()->mapWithKeys(fn (Pack $p) => [$p->id => $p->name]))
                ->required()
                ->searchable(),
        ];

        if (!$credentialsConfigured) {
            $components[] = Placeholder::make('vcenter_warning')
                ->label('vCenter not configured')
                ->content('Set VCENTER_HOST, VCENTER_USER, and VCENTER_PASSWORD in plugin settings before linking packs.');
        }

        $components[] = Select::make('provision_type')
            ->label('Provision Type')
            ->options([
                'clone' => 'Clone from template',
                'iso'   => 'Install from ISO (customer does OS setup)',
            ])
            ->default('clone')
            ->required()
            ->live();

        $components[] = empty($templates)
            ? TextInput::make('template_id')
                ->label('VM Template ID')
                ->required(fn (Get $get) => $get('provision_type') === 'clone')
                ->visible(fn (Get $get) => $get('provision_type') === 'clone')
            : Select::make('template_id')
                ->label('VM Template')
                ->options($templates)
                ->required(fn (Get $get) => $get('provision_type') === 'clone')
                ->visible(fn (Get $get) => $get('provision_type') === 'clone')
                ->searchable();

        $components[] = empty($datastores)
            ? TextInput::make('datastore_id')->label('Datastore ID')->required()
            : Select::make('datastore_id')->label('Datastore')->options($datastores)->required()->searchable();

        $components[] = Select::make('folder_id')
            ->label('VM Folder')
            ->options(function () use ($credentialsConfigured) {
                if (!$credentialsConfigured) {
                    return [];
                }
                try {
                    return self::optionsFrom(app(VCenterService::class)->listFolders());
                } catch (Exception) {
                    return [];
                }
            })
            ->nullable()
            ->searchable()
            ->helperText('Folder where new VMs will be placed. Required for ISO provisioning.');

        $components[] = Select::make('placement_type')
            ->label('Placement target')
            ->options(['cluster' => 'Cluster', 'host' => 'Host (ESXi)'])
            ->default('cluster')
            ->required()
            ->live();

        $components[] = Select::make('cluster_id')
            ->label(fn (Get $get) => $get('placement_type') === 'host' ? 'Host' : 'Cluster')
            ->options(function (Get $get) use ($credentialsConfigured) {
                if (!$credentialsConfigured) {
                    return [];
                }
                try {
                    $vcenter = app(VCenterService::class);
                    return $get('placement_type') === 'host'
                        ? self::optionsFrom($vcenter->listHosts())
                        : self::optionsFrom($vcenter->listClusters());
                } catch (Exception) {
                    return [];
                }
            })
            ->required()
            ->searchable();

        $components[] = Select::make('network_id')
            ->label('Network')
            ->options(function () use ($credentialsConfigured) {
                if (!$credentialsConfigured) {
                    return [];
                }
                try {
                    return self::optionsFrom(app(VCenterService::class)->listNetworks());
                } catch (Exception) {
                    return [];
                }
            })
            ->required(fn (Get $get) => $get('provision_type') === 'iso')
            ->visible(fn (Get $get) => $get('provision_type') === 'iso')
            ->searchable()
            ->helperText('Portgroup the VPS NIC will be attached to. Required for ISO-provisioned VMs (clones inherit the template\'s network).');

        $components[] = TextInput::make('guest_os_id')
            ->label('Guest OS ID')
            ->default('OTHER_LINUX_64')
            ->required(fn (Get $get) => $get('provision_type') === 'iso')
            ->visible(fn (Get $get) => $get('provision_type') === 'iso')
            ->helperText('e.g. DEBIAN_12_64, UBUNTU_64, RHEL_9_64, OTHER_LINUX_64');

        $components[] = Select::make('default_iso_item_id')
            ->label('Default ISO (Content Library item)')
            ->options(function () use ($credentialsConfigured) {
                if (!$credentialsConfigured) {
                    return [];
                }
                try {
                    $vcenter   = app(VCenterService::class);
                    $libraries = $vcenter->listContentLibraries();
                    $options   = [];
                    foreach ($libraries as $library) {
                        foreach ($vcenter->listContentLibraryItems($library['id']) as $item) {
                            $options[$item['id']] = "[{$library['name']}] {$item['name']}";
                        }
                    }
                    return $options;
                } catch (Exception) {
                    return [];
                }
            })
            ->nullable()
            ->searchable()
            ->visible(fn (Get $get) => $get('provision_type') === 'iso')
            ->helperText('The ISO attached when a new VM is provisioned. Customer can swap it later.');

        $components = array_merge($components, [
            TextInput::make('default_cpu')
                ->label('Default CPU cores')
                ->numeric()
                ->minValue(1)
                ->required()
                ->default(2),
            TextInput::make('default_memory_mb')
                ->label('Default RAM (MB)')
                ->numeric()
                ->minValue(512)
                ->required()
                ->default(2048),
            TextInput::make('default_disk_gb')
                ->label('Default Disk (GB)')
                ->numeric()
                ->minValue(1)
                ->required()
                ->default(20),
        ]);

        return $schema->components($components);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('pack.name')
                    ->label('Pack')
                    ->sortable(),
                TextColumn::make('provision_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'iso'   => 'warning',
                        'clone' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'iso'   => 'ISO',
                        'clone' => 'Clone',
                        default => $state,
                    }),
                TextColumn::make('template_id')
                    ->label('Template ID')
                    ->placeholder('—'),
                TextColumn::make('default_cpu')
                    ->label('CPU'),
                TextColumn::make('default_memory_mb')
                    ->label('RAM (MB)'),
                TextColumn::make('default_disk_gb')
                    ->label('Disk (GB)'),
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since(),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListVcenterPackSettings::route('/'),
            'create' => CreateVcenterPackSetting::route('/create'),
            'edit'   => EditVcenterPackSetting::route('/{record}/edit'),
        ];
    }

    /** @return array<string, string> */
    private static function optionsFrom(array $items): array
    {
        return collect($items)->mapWithKeys(fn ($item) => [$item['id'] => $item['name']])->all();
    }
}

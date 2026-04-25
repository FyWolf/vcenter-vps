<?php

namespace Fywolf\VcenterVps\Filament\Admin\Resources\VcenterPackSettings\Pages;

use Fywolf\VcenterVps\Filament\Admin\Resources\VcenterPackSettings\VcenterPackSettingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVcenterPackSettings extends ListRecords
{
    protected static string $resource = VcenterPackSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}

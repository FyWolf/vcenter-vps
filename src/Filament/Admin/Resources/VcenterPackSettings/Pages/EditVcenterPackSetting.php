<?php

namespace Fywolf\VcenterVps\Filament\Admin\Resources\VcenterPackSettings\Pages;

use Fywolf\VcenterVps\Filament\Admin\Resources\VcenterPackSettings\VcenterPackSettingResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditVcenterPackSetting extends EditRecord
{
    protected static string $resource = VcenterPackSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}

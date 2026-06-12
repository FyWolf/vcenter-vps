<?php

namespace Fywolf\VcenterVps\Filament\App\Resources\VpsInstances\Pages;

use Filament\Resources\Pages\ListRecords;
use Fywolf\VcenterVps\Filament\App\Resources\VpsInstances\VpsInstanceResource;

class ListVps extends ListRecords
{
    protected static string $resource = VpsInstanceResource::class;
}

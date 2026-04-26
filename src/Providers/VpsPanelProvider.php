<?php

namespace Fywolf\VcenterVps\Providers;

use App\Providers\Filament\PanelProvider;
use Filament\Facades\Filament;
use Filament\Panel;
use Fywolf\VcenterVps\Http\Middleware\VpsTenantMiddleware;
use Fywolf\VcenterVps\Models\VpsInstance;

class VpsPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return parent::panel($panel)
            ->id('vps')
            ->path('vps')
            ->homeUrl(fn () => Filament::getPanel('app')->getUrl())
            ->tenant(VpsInstance::class, 'id')
            ->tenantMiddleware([VpsTenantMiddleware::class])
            ->discoverPages(
                in: plugin_path('vcenter-vps', 'src/Filament/Vps/Pages'),
                for: 'Fywolf\\VcenterVps\\Filament\\Vps\\Pages'
            );
    }
}

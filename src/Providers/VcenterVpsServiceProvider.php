<?php

namespace Fywolf\VcenterVps\Providers;

use Fywolf\Billing\ProvisionerRegistry;
use Fywolf\VcenterVps\Http\Controllers\VcenterConsoleController;
use Fywolf\VcenterVps\Providers\VpsPanelProvider;
use Fywolf\VcenterVps\Provisioners\VcenterProvisioner;
use Fywolf\VcenterVps\Services\VCenterService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class VcenterVpsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(VCenterService::class);
        $this->app->register(VpsPanelProvider::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'vcenter-vps');

        app(ProvisionerRegistry::class)->register(
            VcenterProvisioner::getSlug(),
            VcenterProvisioner::class
        );

        Route::middleware(['web', 'auth'])
            ->get('/vcenter-vps/console/{instance}', [VcenterConsoleController::class, 'show'])
            ->name('vcenter-vps.console');

        Route::middleware(['web', 'auth'])
            ->get('/vcenter-vps/console/{instance}/ticket', [VcenterConsoleController::class, 'ticket'])
            ->name('vcenter-vps.console.ticket');

        Route::middleware(['web', 'auth'])
            ->get('/vcenter-vps/admin/console/{instance}', [VcenterConsoleController::class, 'adminShow'])
            ->name('vcenter-vps.admin.console');

        Route::middleware(['web', 'auth'])
            ->get('/vcenter-vps/admin/console/{instance}/ticket', [VcenterConsoleController::class, 'adminTicket'])
            ->name('vcenter-vps.admin.console.ticket');
    }
}

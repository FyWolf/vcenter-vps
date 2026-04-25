<?php

namespace Fywolf\VcenterVps\Providers;

use Fywolf\Billing\ProvisionerRegistry;
use Fywolf\VcenterVps\Http\Controllers\VcenterConsoleController;
use Fywolf\VcenterVps\Provisioners\VcenterProvisioner;
use Fywolf\VcenterVps\Services\VCenterService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class VcenterVpsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(VCenterService::class);
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

        Route::get('/vcenter-vps/wmks.js', [VcenterConsoleController::class, 'wmks'])
            ->name('vcenter-vps.wmks');
    }
}

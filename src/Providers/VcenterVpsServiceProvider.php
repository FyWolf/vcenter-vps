<?php

namespace Fywolf\VcenterVps\Providers;

use Fywolf\Billing\ProvisionerRegistry;
use Fywolf\VcenterVps\Provisioners\VcenterProvisioner;
use Fywolf\VcenterVps\Services\VCenterService;
use Illuminate\Support\ServiceProvider;

class VcenterVpsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(VCenterService::class);
    }

    public function boot(): void
    {
        app(ProvisionerRegistry::class)->register(
            VcenterProvisioner::getSlug(),
            VcenterProvisioner::class
        );
    }
}

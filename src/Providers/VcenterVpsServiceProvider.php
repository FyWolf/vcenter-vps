<?php

namespace Fywolf\VcenterVps\Providers;

use App\Models\ApiKey;
use Fywolf\VcenterVps\Billing\BillingClient;
use Fywolf\VcenterVps\Http\Controllers\Api\VpsController;
use Fywolf\VcenterVps\Http\Controllers\VcenterConsoleController;
use Fywolf\VcenterVps\Services\VCenterService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class VcenterVpsServiceProvider extends ServiceProvider
{
    /**
     * The ACL resource the billing endpoints are gated on.
     *
     * Its own resource rather than the bridge's `billing`, for the same reason
     * the bridge does not use `server`: these routes can destroy virtual
     * machines, and that capability should be grantable separately from
     * "provision game servers". The billing service's key needs both.
     */
    public const RESOURCE_NAME = 'vps';

    public function register(): void
    {
        $this->app->singleton(VCenterService::class);
        $this->app->singleton(BillingClient::class);

        ApiKey::registerCustomResourceName(self::RESOURCE_NAME);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'vcenter-vps');

        $this->registerBillingRoutes();
        $this->registerConsoleRoutes();
    }

    /**
     * Where `app(ProvisionerRegistry::class)->register(...)` used to be.
     *
     * Billing lives outside the panel now and reaches the provisioner over HTTP.
     * Same prefix and the same auth as the billing-bridge plugin's endpoints, so
     * the billing service holds one `papp_` key for both.
     */
    private function registerBillingRoutes(): void
    {
        Route::prefix('api/application/billing/vps')
            ->middleware(['auth:sanctum', 'throttle:120,1'])
            ->withoutMiddleware(['web', 'auth', 'verify-csrf-token', 'App\Http\Middleware\VerifyCsrfToken'])
            ->group(function () {
                Route::post('/', [VpsController::class, 'store'])
                    ->name('vcenter-vps.api.store');

                Route::post('/sync', [VpsController::class, 'sync'])
                    ->name('vcenter-vps.api.sync');

                // Orders are addressed by billing's order id, not the local
                // instance id — provisioning is async, so the instance may not
                // exist yet when billing first needs to refer to it.
                Route::get('/{order}', [VpsController::class, 'show'])
                    ->whereNumber('order')->name('vcenter-vps.api.show');

                Route::post('/{order}/suspend', [VpsController::class, 'suspend'])
                    ->whereNumber('order')->name('vcenter-vps.api.suspend');

                Route::post('/{order}/unsuspend', [VpsController::class, 'unsuspend'])
                    ->whereNumber('order')->name('vcenter-vps.api.unsuspend');

                Route::patch('/{order}/plan', [VpsController::class, 'applyPlan'])
                    ->whereNumber('order')->name('vcenter-vps.api.plan');

                Route::delete('/{order}', [VpsController::class, 'destroy'])
                    ->whereNumber('order')->name('vcenter-vps.api.destroy');

                // Collaborators. Billing owns the invitation and is the only
                // writer — the panel deliberately offers no screen for these, so
                // there is nothing to reconcile between the two sides.
                Route::post('/{order}/users', [VpsController::class, 'storeCollaborator'])
                    ->whereNumber('order')->name('vcenter-vps.api.users.store');

                Route::delete('/{order}/users/{user}', [VpsController::class, 'destroyCollaborator'])
                    ->whereNumber('order')->whereNumber('user')
                    ->name('vcenter-vps.api.users.destroy');
            });
    }

    private function registerConsoleRoutes(): void
    {
        Route::middleware(['web', 'auth'])
            ->prefix('vcenter-vps')
            ->name('vcenter-vps.')
            ->group(function () {
                Route::get('/console/{instance}', [VcenterConsoleController::class, 'show'])
                    ->whereNumber('instance')->name('console');

                Route::get('/console/{instance}/ticket', [VcenterConsoleController::class, 'ticket'])
                    ->whereNumber('instance')->name('console.ticket');

                Route::get('/admin/console/{instance}', [VcenterConsoleController::class, 'adminShow'])
                    ->whereNumber('instance')->name('admin.console');

                Route::get('/admin/console/{instance}/ticket', [VcenterConsoleController::class, 'adminTicket'])
                    ->whereNumber('instance')->name('admin.console.ticket');
            });
    }
}

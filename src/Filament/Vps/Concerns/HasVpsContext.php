<?php

namespace Fywolf\VcenterVps\Filament\Vps\Concerns;

use Fywolf\Billing\Enums\OrderStatus;
use Fywolf\Billing\Models\Customer;
use Fywolf\VcenterVps\Models\VpsInstance;
use Illuminate\Database\Eloquent\Model;

trait HasVpsContext
{
    public VpsInstance $instance;

    protected function loadInstance(int $vpsId): void
    {
        $customer = Customer::where('user_id', auth()->id())->firstOrFail();

        $this->instance = VpsInstance::whereHas('order', fn ($q) => $q
            ->where('customer_id', $customer->id)
            ->whereIn('status', [OrderStatus::Active, OrderStatus::GracePeriod, OrderStatus::Cancelled])
        )
            ->with(['order.packPrice.pack'])
            ->findOrFail($vpsId);
    }

    public static function getUrl(
        array $parameters = [],
        bool $isAbsolute = true,
        ?string $panel = null,
        ?Model $tenant = null,
    ): string {
        if (!isset($parameters['vpsId'])) {
            $current = request()?->route('vpsId');
            if ($current !== null) {
                $parameters['vpsId'] = $current;
            }
        }

        return parent::getUrl($parameters, $isAbsolute, $panel, $tenant);
    }
}

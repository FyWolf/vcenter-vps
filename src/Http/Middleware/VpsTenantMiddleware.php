<?php

namespace Fywolf\VcenterVps\Http\Middleware;

use Closure;
use Fywolf\Billing\Enums\OrderStatus;
use Fywolf\Billing\Models\Customer;
use Fywolf\VcenterVps\Models\VpsInstance;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VpsTenantMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var VpsInstance $instance */
        $instance = $request->route('tenant');

        $customer = Customer::where('user_id', $request->user()->id)->first();

        abort_if(!$customer, 403);

        $authorized = VpsInstance::whereHas('order', fn ($q) => $q
            ->where('customer_id', $customer->id)
            ->whereIn('status', [OrderStatus::Active, OrderStatus::GracePeriod, OrderStatus::Cancelled])
        )->where('id', $instance->id)->exists();

        abort_unless($authorized, 403);

        return $next($request);
    }
}

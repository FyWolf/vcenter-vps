<?php

namespace Fywolf\VcenterVps\Http\Controllers;

use Fywolf\Billing\Models\Customer;
use Fywolf\VcenterVps\Models\VpsInstance;
use Fywolf\VcenterVps\Services\VCenterService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class VcenterConsoleController extends Controller
{
    public function show(Request $request, int $instance): \Illuminate\Contracts\View\View
    {
        $customer = Customer::where('user_id', $request->user()->id)->firstOrFail();

        $vpsInstance = VpsInstance::whereHas('order', fn ($q) => $q->where('customer_id', $customer->id))
            ->findOrFail($instance);

        $ticket = app(VCenterService::class)->getConsoleTicket($vpsInstance->vm_id);

        // Rewrite wss://vcenter-host/ticket/UUID → wss://panel-host/vcenter-proxy/ticket/UUID
        $parsed    = parse_url($ticket);
        $path      = ltrim($parsed['path'] ?? '', '/');
        $panelHost = $request->getHttpHost();
        $wsScheme  = $request->isSecure() ? 'wss' : 'ws';

        $proxiedUrl = "{$wsScheme}://{$panelHost}/vcenter-proxy/{$path}";

        return view('vcenter-vps::console', [
            'instance'   => $vpsInstance,
            'consoleUrl' => $proxiedUrl,
        ]);
    }

    public function adminShow(Request $request, int $instance): \Illuminate\Contracts\View\View
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $vpsInstance = VpsInstance::findOrFail($instance);

        $ticket = app(VCenterService::class)->getConsoleTicket($vpsInstance->vm_id);

        $parsed    = parse_url($ticket);
        $path      = ltrim($parsed['path'] ?? '', '/');
        $panelHost = $request->getHttpHost();
        $wsScheme  = $request->isSecure() ? 'wss' : 'ws';

        $proxiedUrl = "{$wsScheme}://{$panelHost}/vcenter-proxy/{$path}";

        return view('vcenter-vps::console', [
            'instance'   => $vpsInstance,
            'consoleUrl' => $proxiedUrl,
        ]);
    }
}

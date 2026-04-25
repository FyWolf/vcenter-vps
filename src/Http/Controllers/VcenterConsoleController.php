<?php

namespace Fywolf\VcenterVps\Http\Controllers;

use Fywolf\Billing\Models\Customer;
use Fywolf\VcenterVps\Models\VpsInstance;
use Fywolf\VcenterVps\Services\VCenterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class VcenterConsoleController extends Controller
{
    public function show(Request $request, int $instance): \Illuminate\Contracts\View\View
    {
        $customer = Customer::where('user_id', $request->user()->id)->firstOrFail();

        $vpsInstance = VpsInstance::whereHas('order', fn ($q) => $q->where('customer_id', $customer->id))
            ->findOrFail($instance);

        return view('vcenter-vps::console', [
            'instance'    => $vpsInstance,
            'ticketRoute' => route('vcenter-vps.console.ticket', $instance),
        ]);
    }

    public function ticket(Request $request, int $instance): JsonResponse
    {
        $customer = Customer::where('user_id', $request->user()->id)->firstOrFail();

        $vpsInstance = VpsInstance::whereHas('order', fn ($q) => $q->where('customer_id', $customer->id))
            ->findOrFail($instance);

        return response()->json(['url' => $this->buildProxiedUrl($request, $vpsInstance->vm_id)]);
    }

    public function adminShow(Request $request, int $instance): \Illuminate\Contracts\View\View
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $vpsInstance = VpsInstance::findOrFail($instance);

        return view('vcenter-vps::console', [
            'instance'    => $vpsInstance,
            'ticketRoute' => route('vcenter-vps.admin.console.ticket', $instance),
        ]);
    }

    public function adminTicket(Request $request, int $instance): JsonResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $vpsInstance = VpsInstance::findOrFail($instance);

        return response()->json(['url' => $this->buildProxiedUrl($request, $vpsInstance->vm_id)]);
    }

    private function buildProxiedUrl(Request $request, string $vmId): string
    {
        $ticket    = app(VCenterService::class)->getConsoleTicket($vmId);
        $parsed    = parse_url($ticket);
        $path      = ltrim($parsed['path'] ?? '', '/');
        $panelHost = $request->getHttpHost();
        $wsScheme  = $request->isSecure() ? 'wss' : 'ws';

        return "{$wsScheme}://{$panelHost}/vcenter-proxy/{$path}";
    }
}

<?php

namespace Fywolf\VcenterVps\Http\Controllers;

use Fywolf\VcenterVps\Models\VpsInstance;
use Fywolf\VcenterVps\Models\VpsInstanceUser;
use Fywolf\VcenterVps\Services\VCenterService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Ownership used to be proven by loading billing's Customer for the panel user
 * and then filtering instances through `order.customer_id`. Both of those lived
 * in this database; neither does now.
 *
 * `ownedBy()` replaces the pair with a comparison against the local `user_id`
 * column that billing set at provision time. Beyond being the only option that
 * still works, it means a billing outage cannot cost a customer access to a
 * machine that is running perfectly well.
 */
class VcenterConsoleController extends Controller
{
    public function show(Request $request, int $instance): View
    {
        $vpsInstance = $this->authorizedInstance($request, $instance);

        return view('vcenter-vps::console', [
            'instance'    => $vpsInstance,
            'ticketRoute' => route('vcenter-vps.console.ticket', $instance),
        ]);
    }

    public function ticket(Request $request, int $instance): JsonResponse
    {
        $vpsInstance = $this->authorizedInstance($request, $instance);

        return response()->json(['url' => $this->buildProxiedUrl($request, $vpsInstance->vm_id)]);
    }

    public function adminShow(Request $request, int $instance): View
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

    /**
     * The console is reachable by the owner, or by a collaborator granted it.
     *
     * `accessibleBy` widens *who*, and `userCan` narrows to *what* — a
     * collaborator may hold power rights without console rights, and the console
     * is the one that puts a keyboard on the machine. Both are needed: the scope
     * alone would let anybody the VPS is shared with open a terminal on it.
     */
    private function authorizedInstance(Request $request, int $instance): VpsInstance
    {
        $vpsInstance = VpsInstance::query()
            ->accessibleBy((int) $request->user()->id)
            ->findOrFail($instance);

        abort_unless(
            $vpsInstance->userCan((int) $request->user()->id, VpsInstanceUser::CONSOLE),
            403,
        );

        return $vpsInstance;
    }

    private function buildProxiedUrl(Request $request, string $vmId): string
    {
        $ticket    = app(VCenterService::class)->getConsoleTicket($vmId);
        $parsed    = parse_url($ticket);
        $path      = ltrim($parsed['path'] ?? '', '/');
        $query     = isset($parsed['query']) ? '?' . $parsed['query'] : '';
        $panelHost = $request->getHttpHost();
        $wsScheme  = $request->isSecure() ? 'wss' : 'ws';

        return "{$wsScheme}://{$panelHost}/vcenter-proxy/{$path}{$query}";
    }
}

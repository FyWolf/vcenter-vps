<?php

namespace Fywolf\VcenterVps\Http\Controllers;

use Fywolf\Billing\Models\Customer;
use Fywolf\VcenterVps\Models\VpsInstance;
use Fywolf\VcenterVps\Services\VCenterService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;

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

    /**
     * Serve the WMKS JavaScript library fetched directly from vCenter.
     * Tries several paths used across vCenter 6/7/8, caches the result.
     */
    public function wmks(): Response
    {
        $js = cache()->remember('vcenter-vps.wmks-js', 3600, function () {
            $host     = rtrim(config('vcenter-vps.host'), '/');
            $insecure = (bool) config('vcenter-vps.insecure');

            $candidates = [
                '/vsphere-client/webconsole/api/wmks/lib/wmks.min.js',
                '/vsphere-client/webconsole/api/wmks/wmks.min.js',
                '/vsphere-client/webconsole/api/wmks-full.min.js',
                '/vsphere-client/webconsole/vendor/wmks/wmks.min.js',
            ];

            foreach ($candidates as $path) {
                try {
                    $req = Http::timeout(10);
                    if ($insecure) {
                        $req = $req->withoutVerifying();
                    }
                    $resp = $req->get($host . $path);

                    // A real JS file will be at least 10 KB
                    if ($resp->successful() && strlen($resp->body()) > 10240) {
                        return $resp->body();
                    }
                } catch (\Throwable) {
                    continue;
                }
            }

            return null;
        });

        if ($js === null) {
            return response(
                '/* vcenter-vps: wmks.min.js not found on vCenter. Check that the path exists. */',
                404,
                ['Content-Type' => 'application/javascript']
            );
        }

        return response($js, 200, [
            'Content-Type'  => 'application/javascript',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}

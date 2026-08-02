<?php

namespace Fywolf\VcenterVps\Http\Requests;

/**
 * The order as billing describes it — used for both provisioning and the
 * periodic sync, because the plugin caches the same facts either way.
 */
class ProvisionVpsRequest extends VpsApiRequest
{
    /**
     * @return array<string, string[]>
     */
    public function rules(): array
    {
        return [
            'order_id' => ['required', 'integer', 'min:1'],
            'pack_id'  => ['required', 'integer', 'min:1'],
            // The *panel* user id. Billing resolves it from the customer's panel
            // account; this plugin has no way to derive it, and console access is
            // authorized against it.
            'user_id'        => ['required', 'integer', 'min:1'],
            'pack_name'      => ['nullable', 'string', 'max:255'],
            'customer_label' => ['nullable', 'string', 'max:255'],
            'status'         => ['nullable', 'string', 'max:64'],
            'expires_at'     => ['nullable', 'date'],
            'cores'          => ['nullable', 'integer', 'min:1'],
            'memory_mb'      => ['nullable', 'integer', 'min:1'],
            'disk_gb'        => ['nullable', 'integer', 'min:1'],
        ];
    }
}

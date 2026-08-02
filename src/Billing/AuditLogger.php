<?php

namespace Fywolf\VcenterVps\Billing;

/**
 * Drop-in replacement for `Fywolf\Billing\Models\AuditLog::record()`.
 *
 * Same three-argument shape as the old static call so the provisioner and the
 * ISO job read the way they did, except the third argument is now a billing
 * order id rather than an Order model — the plugin no longer has the model.
 */
class AuditLogger
{
    /**
     * @param  array<string, mixed>  $context
     */
    public static function record(string $event, array $context = [], ?int $billingOrderId = null): void
    {
        app(BillingClient::class)->recordAudit($event, $context, $billingOrderId);
    }
}

<?php

namespace Fywolf\VcenterVps\Jobs;

use Fywolf\VcenterVps\Billing\AuditLogger;
use Fywolf\VcenterVps\Billing\Data\OrderData;
use Fywolf\VcenterVps\Provisioners\VcenterProvisioner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Cloning a template or building a VM from an ISO takes minutes, which is far
 * longer than billing should hold an HTTP request open. The webhook accepts the
 * order and hands off to here.
 *
 * Retries are safe: `provision()` returns early when an instance already exists
 * for the order, so a retry after a timeout cannot produce a second VM.
 */
class ProvisionVpsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 1800;

    /** Space the retries out — vCenter task contention is usually transient. */
    public array $backoff = [60, 300];

    public function __construct(private readonly OrderData $order) {}

    public function handle(VcenterProvisioner $provisioner): void
    {
        $provisioner->provision($this->order);
    }

    public function failed(Throwable $e): void
    {
        AuditLogger::record('vps_provisioning_failed', [
            'error'   => $e->getMessage(),
            'pack_id' => $this->order->packId,
        ], $this->order->orderId);
    }
}

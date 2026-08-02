<?php

namespace Fywolf\VcenterVps\Provisioners;

use Exception;
use Fywolf\VcenterVps\Billing\AuditLogger;
use Fywolf\VcenterVps\Billing\Data\OrderData;
use Fywolf\VcenterVps\Filament\App\Resources\VpsInstances\VpsInstanceResource;
use Fywolf\VcenterVps\Models\VcenterPackSetting;
use Fywolf\VcenterVps\Models\VpsInstance;
use Fywolf\VcenterVps\Services\VCenterService;
use RuntimeException;

/**
 * VM lifecycle, driven by billing.
 *
 * This used to implement `Fywolf\Billing\Contracts\PackProvisionerContract` and
 * be resolved out of billing's ProvisionerRegistry in-process. Billing is a
 * separate service now, so the same four operations are reached over HTTP
 * instead — see BillingWebhookController, which is the only caller.
 *
 * The signatures changed accordingly: `provision()` takes the order as a value
 * object billing sent, and the other three take a billing order id, because
 * finding the instance is all they ever needed the order for.
 */
class VcenterProvisioner
{
    public function __construct(private VCenterService $vcenter) {}

    public static function getSlug(): string
    {
        return 'vcenter';
    }

    public static function getLabel(): string
    {
        return 'vCenter VPS';
    }

    public function isProvisioned(int $billingOrderId): bool
    {
        return VpsInstance::where('billing_order_id', $billingOrderId)->exists();
    }

    public function provision(OrderData $order): void
    {
        if ($this->isProvisioned($order->orderId)) {
            return;
        }

        $setting = VcenterPackSetting::where('billing_pack_id', $order->packId)->first();

        if (!$setting) {
            throw new RuntimeException(
                "No vCenter pack setting found for pack #{$order->packId}."
            );
        }

        try {
            $setting->isIsoProvision()
                ? $this->provisionFromIso($order, $setting)
                : $this->provisionFromClone($order, $setting);
        } catch (Exception $e) {
            AuditLogger::record('vps_provisioning_failed', ['error' => $e->getMessage()], $order->orderId);

            throw $e;
        }
    }

    public function suspend(int $billingOrderId): void
    {
        $instance = $this->findInstance($billingOrderId);

        if (!$instance) {
            return;
        }

        try {
            $this->vcenter->powerOff($instance->vm_id);

            $instance->update([
                'state_cache'      => 'POWERED_OFF',
                'state_checked_at' => now('UTC'),
            ]);

            AuditLogger::record('vps_suspended', ['vm_id' => $instance->vm_id], $billingOrderId);
        } catch (Exception $e) {
            report($e);
        }
    }

    public function unsuspend(int $billingOrderId): void
    {
        $instance = $this->findInstance($billingOrderId);

        if (!$instance) {
            return;
        }

        try {
            $this->vcenter->powerOn($instance->vm_id);

            $instance->update([
                'state_cache'      => 'POWERED_ON',
                'state_checked_at' => now('UTC'),
            ]);

            AuditLogger::record('vps_unsuspended', ['vm_id' => $instance->vm_id], $billingOrderId);
        } catch (Exception $e) {
            report($e);
        }
    }

    public function terminate(int $billingOrderId): void
    {
        // VM deletion can be done manually from the admin panel
    }

    /**
     * Apply a changed plan to an existing VM.
     *
     * VCenterService has no reconfigure call, and a VPS resize is not the
     * unconditional operation a game server's limits are: CPU and memory need
     * hot-add enabled or a power cycle, and a disk can be grown but never shrunk
     * — and growing one still leaves the guest filesystem to whoever runs it.
     *
     * So this reports honestly rather than pretending. It deliberately does *not*
     * update the cached `spec_*` columns: those describe what the machine
     * actually is, and moving them here would show the customer an upgrade that
     * had not happened. Billing's order remains the record of what was bought.
     *
     * Silently accepting a plan change that never reaches the machine is the bug
     * this codebase already fixed once for game servers.
     *
     * @param  array<string, mixed>  $spec
     * @return array<string, mixed>
     */
    public function applyPlan(int $billingOrderId, array $spec): array
    {
        $instance = $this->findInstance($billingOrderId);

        if (!$instance) {
            return ['applied' => [], 'requires_manual_action' => [], 'reason' => 'no_instance'];
        }

        $wanted = array_filter(
            [
                'cores'     => $spec['cores']     ?? null,
                'memory_mb' => $spec['memory_mb'] ?? null,
                'disk_gb'   => $spec['disk_gb']   ?? null,
            ],
            fn ($value) => $value !== null,
        );

        AuditLogger::record('vps_plan_change_requires_manual_resize', [
            'vm_id'   => $instance->vm_id,
            'current' => [
                'cores'     => $instance->spec_cores,
                'memory_mb' => $instance->spec_memory_mb,
                'disk_gb'   => $instance->spec_disk_gb,
            ],
            'wanted' => $wanted,
        ], $billingOrderId);

        return [
            'applied'                => [],
            'requires_manual_action' => array_keys($wanted),
            'reason'                 => 'vcenter_resize_not_automated',
        ];
    }

    /**
     * Refresh the cached billing facts on an existing instance.
     *
     * Billing calls this when an order's status or expiry changes, so the
     * customer list and admin table stay truthful without the plugin polling.
     */
    public function syncOrder(OrderData $order): void
    {
        $this->findInstance($order->orderId)?->update($order->toInstanceColumns());
    }

    public function getManagementUrl(int $billingOrderId): ?string
    {
        $instance = $this->findInstance($billingOrderId);

        if (!$instance) {
            return null;
        }

        return VpsInstanceResource::getUrl('view', ['record' => $instance], panel: 'app');
    }

    public function findInstance(int $billingOrderId): ?VpsInstance
    {
        return VpsInstance::where('billing_order_id', $billingOrderId)->first();
    }

    private function provisionFromClone(OrderData $order, VcenterPackSetting $setting): void
    {
        $vmId = $this->vcenter->cloneVm([
            'name'           => "vps-order-{$order->orderId}",
            'template_id'    => $setting->template_id,
            'datastore_id'   => $setting->datastore_id,
            'folder_id'      => $setting->folder_id,
            'cluster_id'     => $setting->cluster_id,
            'placement_type' => $setting->placement_type,
            'cpu'            => $setting->default_cpu,
            'memory_mb'      => $setting->default_memory_mb,
        ]);

        VpsInstance::create($order->toInstanceColumns() + [
            'vm_id'       => $vmId,
            'state_cache' => 'POWERED_ON',
        ]);

        AuditLogger::record('vps_provisioned', [
            'provision_type' => 'clone',
            'vm_id'          => $vmId,
            'template_id'    => $setting->template_id,
        ], $order->orderId);
    }

    private function provisionFromIso(OrderData $order, VcenterPackSetting $setting): void
    {
        $vmId = $this->vcenter->createVm([
            'name'           => "vps-order-{$order->orderId}",
            'cluster_id'     => $setting->cluster_id,
            'placement_type' => $setting->placement_type,
            'datastore_id'   => $setting->datastore_id,
            'folder_id'      => $setting->folder_id,
            'cpu'            => $order->cores    ?? $setting->default_cpu,
            'memory_mb'      => $order->memoryMb ?? $setting->default_memory_mb,
            'guest_os_id'    => $setting->guest_os_id,
        ]);

        $this->vcenter->addDisk($vmId, $order->diskGb ?? $setting->default_disk_gb);

        // CDROM needs SATA — pre-create it so the bus is ready
        $this->vcenter->ensureSataController($vmId);

        if ($setting->network_id) {
            $this->vcenter->addNetworkAdapter($vmId, $setting->network_id);
        }

        $cdromId = null;
        if ($setting->default_iso_item_id) {
            $cdromId = $this->vcenter->addCdromFromLibrary($vmId, $setting->default_iso_item_id);
            // Boot from CD first so the empty disk doesn't dead-end the install
            try {
                $this->vcenter->setBootOrder($vmId, ['CDROM', 'DISK', 'ETHERNET']);
            } catch (Exception) {
                // Boot order is best-effort; default firmware order usually falls through to CD anyway
            }
        }

        $this->vcenter->powerOn($vmId);

        VpsInstance::create($order->toInstanceColumns() + [
            'vm_id'          => $vmId,
            'state_cache'    => 'POWERED_ON',
            'install_status' => VpsInstance::INSTALL_PENDING,
            'iso_item_id'    => $setting->default_iso_item_id,
            'cdrom_id'       => $cdromId,
        ]);

        AuditLogger::record('vps_provisioned', [
            'provision_type' => 'iso',
            'vm_id'          => $vmId,
            'iso_item_id'    => $setting->default_iso_item_id,
            'guest_os_id'    => $setting->guest_os_id,
        ], $order->orderId);
    }
}

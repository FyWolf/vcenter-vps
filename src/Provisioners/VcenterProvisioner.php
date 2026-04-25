<?php

namespace Fywolf\VcenterVps\Provisioners;

use Exception;
use Fywolf\Billing\Contracts\PackProvisionerContract;
use Fywolf\Billing\Models\AuditLog;
use Fywolf\Billing\Models\Order;
use Fywolf\VcenterVps\Filament\App\Pages\MyVps;
use Fywolf\VcenterVps\Models\VcenterPackSetting;
use Fywolf\VcenterVps\Models\VpsInstance;
use Fywolf\VcenterVps\Services\VCenterService;

class VcenterProvisioner implements PackProvisionerContract
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

    public function isProvisioned(Order $order): bool
    {
        return VpsInstance::where('order_id', $order->id)->exists();
    }

    public function provision(Order $order): void
    {
        if ($this->isProvisioned($order)) {
            return;
        }

        $setting = VcenterPackSetting::where('pack_id', $order->packPrice->pack_id)->first();

        if (!$setting) {
            throw new \RuntimeException(
                "No vCenter pack setting found for pack #{$order->packPrice->pack_id}."
            );
        }

        try {
            $setting->isIsoProvision()
                ? $this->provisionFromIso($order, $setting)
                : $this->provisionFromClone($order, $setting);
        } catch (Exception $e) {
            AuditLog::record('vps_provisioning_failed', ['error' => $e->getMessage()], $order);

            throw $e;
        }
    }

    public function suspend(Order $order): void
    {
        $instance = VpsInstance::where('order_id', $order->id)->first();

        if (!$instance) {
            return;
        }

        try {
            $this->vcenter->powerOff($instance->vm_id);

            $instance->update([
                'state_cache'      => 'POWERED_OFF',
                'state_checked_at' => now('UTC'),
            ]);

            AuditLog::record('vps_suspended', ['vm_id' => $instance->vm_id], $order);
        } catch (Exception $e) {
            report($e);
        }
    }

    public function unsuspend(Order $order): void
    {
        $instance = VpsInstance::where('order_id', $order->id)->first();

        if (!$instance) {
            return;
        }

        try {
            $this->vcenter->powerOn($instance->vm_id);

            $instance->update([
                'state_cache'      => 'POWERED_ON',
                'state_checked_at' => now('UTC'),
            ]);

            AuditLog::record('vps_unsuspended', ['vm_id' => $instance->vm_id], $order);
        } catch (Exception $e) {
            report($e);
        }
    }

    public function terminate(Order $order): void
    {
        // VM deletion can be done manually from the admin panel
    }

    public function getManagementUrl(Order $order): ?string
    {
        if (!$this->isProvisioned($order)) {
            return null;
        }

        return MyVps::getUrl(panel: 'app');
    }

    private function provisionFromClone(Order $order, VcenterPackSetting $setting): void
    {
        $vmId = $this->vcenter->cloneVm([
            'name'           => "vps-order-{$order->id}",
            'template_id'    => $setting->template_id,
            'datastore_id'   => $setting->datastore_id,
            'folder_id'      => $setting->folder_id,
            'cluster_id'     => $setting->cluster_id,
            'placement_type' => $setting->placement_type,
            'cpu'            => $setting->default_cpu,
            'memory_mb'      => $setting->default_memory_mb,
        ]);

        VpsInstance::create([
            'order_id'    => $order->id,
            'vm_id'       => $vmId,
            'state_cache' => 'POWERED_ON',
        ]);

        AuditLog::record('vps_provisioned', [
            'provision_type' => 'clone',
            'vm_id'          => $vmId,
            'template_id'    => $setting->template_id,
        ], $order);
    }

    private function provisionFromIso(Order $order, VcenterPackSetting $setting): void
    {
        $price = $order->packPrice;

        $vmId = $this->vcenter->createVm([
            'name'           => "vps-order-{$order->id}",
            'cluster_id'     => $setting->cluster_id,
            'placement_type' => $setting->placement_type,
            'datastore_id'   => $setting->datastore_id,
            'folder_id'      => $setting->folder_id,
            'cpu'            => $price->cores  ?? $setting->default_cpu,
            'memory_mb'      => $price->memory ?? $setting->default_memory_mb,
            'guest_os_id'    => $setting->guest_os_id,
        ]);

        $this->vcenter->addDisk($vmId, $price->disk ?? $setting->default_disk_gb);

        $cdromId = null;
        if ($setting->default_iso_item_id) {
            $cdromId = $this->vcenter->addCdromFromLibrary($vmId, $setting->default_iso_item_id);
        }

        $this->vcenter->powerOn($vmId);

        VpsInstance::create([
            'order_id'       => $order->id,
            'vm_id'          => $vmId,
            'state_cache'    => 'POWERED_ON',
            'install_status' => VpsInstance::INSTALL_PENDING,
            'iso_item_id'    => $setting->default_iso_item_id,
            'cdrom_id'       => $cdromId,
        ]);

        AuditLog::record('vps_provisioned', [
            'provision_type' => 'iso',
            'vm_id'          => $vmId,
            'iso_item_id'    => $setting->default_iso_item_id,
            'guest_os_id'    => $setting->guest_os_id,
        ], $order);
    }
}

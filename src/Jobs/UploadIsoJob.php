<?php

namespace Fywolf\VcenterVps\Jobs;

use Exception;
use Fywolf\Billing\Models\AuditLog;
use Fywolf\VcenterVps\Models\VpsInstance;
use Fywolf\VcenterVps\Services\VCenterService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class UploadIsoJob implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public string $displayName = 'vcenter-vps:upload-iso';

    public int $tries = 2;

    public int $timeout = 3600;

    public function __construct(
        public int $instanceId,
        public string $libraryId,
        public string $isoSource,
        public string $isoSourceType, // 'url' | 'storage'
    ) {}

    public function handle(): void
    {
        $instance = VpsInstance::findOrFail($this->instanceId);
        $service  = app(VCenterService::class);
        $itemName = "customer-iso-order-{$instance->order_id}-" . now()->format('YmdHis');

        $itemId = $this->isoSourceType === 'url'
            ? $this->handleUrlPull($service, $itemName)
            : $this->handleFilePush($service, $itemName);

        if ($instance->cdrom_id) {
            $service->swapCdromToLibraryItem($instance->vm_id, $instance->cdrom_id, $itemId);
        } else {
            $cdromId = $service->addCdromFromLibrary($instance->vm_id, $itemId);
            $instance->update(['cdrom_id' => $cdromId]);
        }

        $instance->update(['iso_item_id' => $itemId]);

        AuditLog::record('vps_iso_swapped', [
            'vm_id'       => $instance->vm_id,
            'iso_item_id' => $itemId,
            'source_type' => $this->isoSourceType,
        ], $instance->order);
    }

    public function failed(Throwable $exception): void
    {
        if ($this->isoSourceType === 'storage') {
            Storage::delete($this->isoSource);
        }

        $instance = VpsInstance::find($this->instanceId);
        if ($instance) {
            AuditLog::record('vps_iso_upload_failed', [
                'error' => $exception->getMessage(),
            ], $instance->order);
        }
    }

    private function handleUrlPull(VCenterService $service, string $itemName): string
    {
        $transfer = $service->startPullTransfer($this->libraryId, $itemName, $this->isoSource);

        $deadline = now()->addHour();
        while (now()->lt($deadline)) {
            $status = $service->getSessionFileStatus($transfer['session_id'], $transfer['filename']);

            if ($status === 'READY') {
                $service->completeUpdateSession($transfer['session_id']);
                return $transfer['item_id'];
            }

            if ($status === 'ERROR') {
                throw new Exception("vCenter PULL transfer failed for URL: {$this->isoSource}");
            }

            sleep(30);
        }

        throw new Exception("vCenter PULL transfer timed out after 1 hour");
    }

    private function handleFilePush(VCenterService $service, string $itemName): string
    {
        $localPath = $this->copyToLocal();

        try {
            return $service->uploadIsoToLibrary($this->libraryId, $itemName, $localPath);
        } finally {
            @unlink($localPath);
            Storage::delete($this->isoSource);
        }
    }

    private function copyToLocal(): string
    {
        $localPath = storage_path('app/iso-uploads/' . Str::uuid() . '.iso');
        @mkdir(dirname($localPath), 0755, true);

        $source = Storage::readStream($this->isoSource);
        if (!$source) {
            throw new Exception("Failed to read uploaded ISO from temporary storage");
        }

        $dest = fopen($localPath, 'wb');
        try {
            stream_copy_to_stream($source, $dest);
        } finally {
            fclose($dest);
            fclose($source);
        }

        return $localPath;
    }
}

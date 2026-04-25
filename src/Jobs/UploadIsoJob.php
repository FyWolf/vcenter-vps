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
use Illuminate\Support\Facades\Http;
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

        $localPath = $this->fetchIso();

        try {
            $itemName = "customer-iso-order-{$instance->order_id}-" . now()->format('YmdHis');

            $itemId = app(VCenterService::class)->uploadIsoToLibrary(
                $this->libraryId,
                $itemName,
                $localPath
            );

            if ($instance->cdrom_id) {
                app(VCenterService::class)->swapCdromToLibraryItem(
                    $instance->vm_id,
                    $instance->cdrom_id,
                    $itemId
                );
            } else {
                $cdromId = app(VCenterService::class)->addCdromFromLibrary($instance->vm_id, $itemId);
                $instance->update(['cdrom_id' => $cdromId]);
            }

            $instance->update(['iso_item_id' => $itemId]);

            AuditLog::record('vps_iso_swapped', [
                'vm_id'          => $instance->vm_id,
                'iso_item_id'    => $itemId,
                'source_type'    => $this->isoSourceType,
            ], $instance->order);
        } finally {
            $this->cleanupLocal($localPath);
        }
    }

    public function failed(Throwable $exception): void
    {
        $instance = VpsInstance::find($this->instanceId);

        if ($instance) {
            AuditLog::record('vps_iso_upload_failed', [
                'error' => $exception->getMessage(),
            ], $instance->order);
        }

        $this->cleanupLocal(null);
    }

    private function fetchIso(): string
    {
        $tmpPath = storage_path('app/iso-uploads/' . Str::uuid() . '.iso');
        @mkdir(dirname($tmpPath), 0755, true);

        if ($this->isoSourceType === 'storage') {
            $contents = Storage::get($this->isoSource);
            file_put_contents($tmpPath, $contents);
            Storage::delete($this->isoSource);
            return $tmpPath;
        }

        // URL download — stream to avoid memory exhaustion
        $response = Http::withOptions(['stream' => true])
            ->timeout(3600)
            ->get($this->isoSource);

        if (!$response->successful()) {
            throw new Exception("Failed to download ISO from URL: " . $response->status());
        }

        $fp = fopen($tmpPath, 'wb');
        try {
            $body = $response->toPsrResponse()->getBody();
            while (!$body->eof()) {
                fwrite($fp, $body->read(65536));
            }
        } finally {
            fclose($fp);
        }

        return $tmpPath;
    }

    private function cleanupLocal(?string $path): void
    {
        if ($path && file_exists($path)) {
            @unlink($path);
        }

        if ($this->isoSourceType === 'storage') {
            Storage::delete($this->isoSource);
        }
    }
}

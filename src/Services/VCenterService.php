<?php

namespace Fywolf\VcenterVps\Services;

use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class VCenterService
{
    private string $baseUrl;
    private ?string $sessionToken = null;

    public function __construct()
    {
        $host = config('vcenter-vps.host');
        $this->baseUrl = rtrim($host, '/') . '/api';
    }

    // Authentication

    private function client(): PendingRequest
    {
        if (!$this->sessionToken) {
            $this->authenticate();
        }

        $request = Http::withHeaders(['vmware-api-session-id' => $this->sessionToken])
            ->acceptJson()
            ->asJson();

        if (config('vcenter-vps.insecure')) {
            $request = $request->withoutVerifying();
        }

        return $request;
    }

    private function authenticate(): void
    {
        $request = Http::withBasicAuth(config('vcenter-vps.user'), config('vcenter-vps.password'))
            ->acceptJson();

        if (config('vcenter-vps.insecure')) {
            $request = $request->withoutVerifying();
        }

        $response = $request->post("{$this->baseUrl}/session");

        if (!$response->successful()) {
            throw new Exception("vCenter authentication failed: " . $response->body());
        }

        $this->sessionToken = trim($response->body(), '"');
    }

    // VM lifecycle

    /**
     * Clone a VM from a template.
     *
     * @param array{
     *   name: string,
     *   template_id: string,
     *   datastore_id: string,
     *   cluster_id: string,
     *   cpu: int,
     *   memory_mb: int,
     * } $params
     * @return string The new VM's ID (e.g. "vm-42")
     */
    public function cloneVm(array $params): string
    {
        $placementKey = ($params['placement_type'] ?? 'cluster') === 'host' ? 'host' : 'cluster';

        $payload = [
            'name'        => $params['name'],
            'source'      => $params['template_id'],
            'placement'   => array_filter([
                $placementKey => $params['cluster_id'],
                'datastore'   => $params['datastore_id'],
                'folder'      => $params['folder_id'] ?? null,
            ]),
            'hardware_customization' => [
                'cpu_update' => [
                    'num_cpus'           => $params['cpu'],
                    'num_cores_per_socket' => 1,
                ],
                'memory_update' => [
                    'memory' => $params['memory_mb'],
                ],
            ],
            'power_on' => true,
        ];

        $response = $this->client()->post("{$this->baseUrl}/vcenter/vm?action=clone", $payload);

        if (!$response->successful()) {
            throw new Exception("vCenter VM clone failed: " . $response->body());
        }

        return trim($response->body(), '"');
    }

    public function powerOn(string $vmId): void
    {
        $response = $this->client()->post("{$this->baseUrl}/vcenter/vm/{$vmId}/power?action=start");

        if (!$response->successful() && $response->status() !== 400) {
            throw new Exception("vCenter powerOn failed for {$vmId}: " . $response->body());
        }
    }

    public function powerOff(string $vmId): void
    {
        $response = $this->client()->post("{$this->baseUrl}/vcenter/vm/{$vmId}/power?action=stop");

        if (!$response->successful() && $response->status() !== 400) {
            throw new Exception("vCenter powerOff failed for {$vmId}: " . $response->body());
        }
    }

    public function reboot(string $vmId): void
    {
        $response = $this->client()->post("{$this->baseUrl}/vcenter/vm/{$vmId}/power?action=reset");

        if (!$response->successful()) {
            throw new Exception("vCenter reboot failed for {$vmId}: " . $response->body());
        }
    }

    /** @return 'POWERED_ON'|'POWERED_OFF'|'SUSPENDED' */
    public function getState(string $vmId): string
    {
        $response = $this->client()->get("{$this->baseUrl}/vcenter/vm/{$vmId}/power");

        if (!$response->successful()) {
            throw new Exception("vCenter getState failed for {$vmId}: " . $response->body());
        }

        return $response->json('state') ?? 'POWERED_OFF';
    }

    public function getConsoleTicket(string $vmId): string
    {
        $response = $this->client()->post(
            "{$this->baseUrl}/vcenter/vm/{$vmId}/console/tickets",
            ['type' => 'WEBMKS']
        );

        if (!$response->successful()) {
            throw new Exception("vCenter console ticket failed for {$vmId}: " . $response->body());
        }

        return $response->json('ticket');
    }

    // Fresh VM provisioning

    /**
     * Create a blank VM (no OS) with specified hardware.
     *
     * @param array{
     *   name: string,
     *   cluster_id: string,
     *   datastore_id: string,
     *   cpu: int,
     *   memory_mb: int,
     *   guest_os_id: string,
     * } $params
     * @return string VM ID
     */
    public function createVm(array $params): string
    {
        $placementKey = ($params['placement_type'] ?? 'cluster') === 'host' ? 'host' : 'cluster';

        $payload = [
            'name'     => $params['name'],
            'guest_OS' => $params['guest_os_id'],
            'placement' => array_filter([
                $placementKey => $params['cluster_id'],
                'datastore'   => $params['datastore_id'],
                'folder'      => $params['folder_id'] ?? null,
            ]),
            'cpu' => [
                'count'            => $params['cpu'],
                'cores_per_socket' => 1,
            ],
            'memory' => [
                'size_MiB' => $params['memory_mb'],
            ],
        ];

        $response = $this->client()->post("{$this->baseUrl}/vcenter/vm", $payload);

        if (!$response->successful()) {
            throw new Exception("vCenter VM creation failed: " . $response->body());
        }

        return trim($response->body(), '"');
    }

    public function addDisk(string $vmId, int $capacityGb): void
    {
        $payload = [
            'new_vmdk' => [
                'capacity' => $capacityGb * 1024 * 1024 * 1024,
            ],
            'type' => 'SCSI',
        ];

        $response = $this->client()->post("{$this->baseUrl}/vcenter/vm/{$vmId}/hardware/disk", $payload);

        if (!$response->successful()) {
            throw new Exception("vCenter add disk failed for {$vmId}: " . $response->body());
        }
    }

    public function addCdromFromLibrary(string $vmId, string $libraryItemId): string
    {
        $isoPath = $this->resolveContentLibraryIsoPath($libraryItemId);

        $payload = [
            'backing' => [
                'type'     => 'ISO_FILE',
                'iso_file' => $isoPath,
            ],
            'start_connected'    => true,
            'allow_guest_control' => true,
        ];

        $response = $this->client()->post("{$this->baseUrl}/vcenter/vm/{$vmId}/hardware/cdrom", $payload);

        if (!$response->successful()) {
            throw new Exception("vCenter attach ISO failed for {$vmId}: " . $response->body());
        }

        return trim($response->body(), '"');
    }

    public function swapCdromToLibraryItem(string $vmId, string $cdromId, string $libraryItemId): void
    {
        $isoPath = $this->resolveContentLibraryIsoPath($libraryItemId);

        $payload = [
            'backing' => [
                'type'     => 'ISO_FILE',
                'iso_file' => $isoPath,
            ],
            'start_connected' => true,
        ];

        $response = $this->client()->patch("{$this->baseUrl}/vcenter/vm/{$vmId}/hardware/cdrom/{$cdromId}", $payload);

        if (!$response->successful()) {
            throw new Exception("vCenter CDROM swap failed for {$vmId}: " . $response->body());
        }
    }

    private function resolveContentLibraryIsoPath(string $libraryItemId): string
    {
        $response = $this->client()->get(
            "{$this->baseUrl}/content/library/item/{$libraryItemId}/storage"
        );

        if (!$response->successful()) {
            throw new Exception("Failed to get storage info for content library item {$libraryItemId}: " . $response->body());
        }

        $entries = $response->json() ?? [];

        // Pass 1: legacy [DatastoreName] path/file.iso format — usable directly
        foreach ($entries as $entry) {
            foreach ($entry['storage_uris'] ?? [] as $uri) {
                if (str_starts_with($uri, '[')) {
                    return $uri;
                }
            }
        }

        // Pass 2: ds:///vmfs/volumes/<uuid>/<path> — convert via storage_backings
        $datastoreNames = null;
        foreach ($entries as $entry) {
            foreach ($entry['storage_uris'] ?? [] as $uri) {
                if (!preg_match('#^ds:///vmfs/volumes/[^/]+/(.+)$#', $uri, $m)) {
                    continue;
                }

                $relativePath = $m[1];

                $datastoreId = collect($entry['storage_backings'] ?? [])
                    ->firstWhere('type', 'DATASTORE')['datastore_id'] ?? null;

                if (!$datastoreId) {
                    continue;
                }

                $datastoreNames ??= collect($this->listDatastores())->pluck('name', 'id')->all();
                $dsName = $datastoreNames[$datastoreId] ?? null;

                if ($dsName) {
                    return "[{$dsName}] {$relativePath}";
                }
            }
        }

        // Item exists but isn't on a local datastore — likely a subscribed library that hasn't synced yet
        $uncached = collect($entries)->contains(fn ($e) => isset($e['cached']) && !$e['cached']);
        $diagnostic = json_encode(array_map(fn ($e) => [
            'name'         => $e['name'] ?? null,
            'cached'       => $e['cached'] ?? null,
            'storage_uris' => $e['storage_uris'] ?? [],
        ], $entries));

        throw new Exception(
            $uncached
                ? "Content library item {$libraryItemId} is not cached on any datastore. If this is a subscribed library, sync it first in vCenter (Content Libraries → item → Synchronize Item)."
                : "Could not resolve a datastore path for content library item {$libraryItemId}. Storage info: {$diagnostic}"
        );
    }

    // Content Library

    /** @return array<array{id: string, name: string}> */
    public function listContentLibraries(): array
    {
        $response = $this->client()->get("{$this->baseUrl}/content/library");

        if (!$response->successful()) {
            return [];
        }

        return collect($response->json() ?? [])
            ->map(function (string $id) {
                $detail = $this->client()->get("{$this->baseUrl}/content/library/{$id}");
                return $detail->successful()
                    ? ['id' => $id, 'name' => $detail->json('name') ?? $id]
                    : null;
            })
            ->filter()
            ->values()
            ->all();
    }

    /** @return array<array{id: string, name: string}> */
    public function listContentLibraryItems(string $libraryId): array
    {
        $response = $this->client()->get("{$this->baseUrl}/content/library/item", [
            'library_id' => $libraryId,
        ]);

        if (!$response->successful()) {
            return [];
        }

        return collect($response->json() ?? [])
            ->map(function (string $id) {
                $detail = $this->client()->get("{$this->baseUrl}/content/library/item/{$id}");
                if (!$detail->successful()) {
                    return null;
                }
                $type = strtolower($detail->json('type') ?? '');
                if ($type !== 'iso') {
                    return null;
                }
                return ['id' => $id, 'name' => $detail->json('name') ?? $id];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Upload a local ISO file to a Content Library via PUSH transfer.
     * Returns the new library item ID.
     */
    public function uploadIsoToLibrary(string $libraryId, string $itemName, string $filePath): string
    {
        $itemId    = $this->createLibraryItem($libraryId, $itemName);
        $sessionId = $this->createUpdateSession($itemId);

        $fileSize = filesize($filePath);
        $filename  = basename($filePath);

        $addResponse = $this->client()->post(
            "{$this->baseUrl}/content/library/item/update-session/{$sessionId}/file?action=add",
            [
                'name'        => $filename,
                'source_type' => 'PUSH',
                'size'        => $fileSize,
            ]
        );

        if (!$addResponse->successful()) {
            throw new Exception("Content Library file add failed: " . $addResponse->body());
        }

        $uploadUrl = $addResponse->json('upload_endpoint.uri');

        if (!$uploadUrl) {
            throw new Exception("Content Library did not return an upload URL.");
        }

        $stream = fopen($filePath, 'rb');
        try {
            $uploadRequest = Http::withHeaders([
                'vmware-api-session-id' => $this->sessionToken,
                'Content-Type'          => 'application/octet-stream',
                'Content-Length'        => $fileSize,
            ]);

            if (config('vcenter-vps.insecure')) {
                $uploadRequest = $uploadRequest->withoutVerifying();
            }

            $uploadResponse = $uploadRequest->withBody($stream, 'application/octet-stream')->put($uploadUrl);
        } finally {
            fclose($stream);
        }

        if (!$uploadResponse->successful()) {
            throw new Exception("ISO upload failed: " . $uploadResponse->body());
        }

        $this->completeUpdateSession($sessionId);

        return $itemId;
    }

    /**
     * Register a URL for vCenter to pull (download) directly into a Content Library.
     * Returns ['item_id', 'session_id', 'filename'] — session must be completed after PULL finishes.
     */
    public function startPullTransfer(string $libraryId, string $itemName, string $url): array
    {
        $itemId    = $this->createLibraryItem($libraryId, $itemName);
        $sessionId = $this->createUpdateSession($itemId);

        $filename = basename(parse_url($url, PHP_URL_PATH)) ?: 'image.iso';
        if (!str_ends_with(strtolower($filename), '.iso')) {
            $filename .= '.iso';
        }

        $addResponse = $this->client()->post(
            "{$this->baseUrl}/content/library/item/update-session/{$sessionId}/file?action=add",
            [
                'name'            => $filename,
                'source_type'     => 'PULL',
                'source_endpoint' => ['uri' => $url],
            ]
        );

        if (!$addResponse->successful()) {
            throw new Exception("Content Library PULL source registration failed: " . $addResponse->body());
        }

        return [
            'item_id'    => $itemId,
            'session_id' => $sessionId,
            'filename'   => $filename,
        ];
    }

    /**
     * Returns the transfer status of a file within an update session.
     * Possible values: WAITING_FOR_TRANSFER, TRANSFERRING, READY, VALIDATING, ERROR
     */
    public function getSessionFileStatus(string $sessionId, string $filename): string
    {
        $response = $this->client()->get(
            "{$this->baseUrl}/content/library/item/update-session/{$sessionId}/file"
        );

        if (!$response->successful()) {
            return 'ERROR';
        }

        foreach ($response->json() ?? [] as $fileInfo) {
            if (($fileInfo['name'] ?? '') === $filename) {
                return $fileInfo['status'] ?? 'ERROR';
            }
        }

        return 'ERROR';
    }

    private function createLibraryItem(string $libraryId, string $name): string
    {
        $response = $this->client()->post("{$this->baseUrl}/content/library/item", [
            'create_spec' => [
                'library_id' => $libraryId,
                'name'       => $name,
                'type'       => 'iso',
            ],
            'client_token' => (string) \Illuminate\Support\Str::uuid(),
        ]);

        if (!$response->successful()) {
            throw new Exception("Content Library item creation failed: " . $response->body());
        }

        return trim($response->body(), '"');
    }

    private function createUpdateSession(string $itemId): string
    {
        $response = $this->client()->post("{$this->baseUrl}/content/library/item/update-session", [
            'create_spec' => [
                'library_item_id' => $itemId,
                'client_token'    => (string) \Illuminate\Support\Str::uuid(),
            ],
        ]);

        if (!$response->successful()) {
            throw new Exception("Content Library update session creation failed: " . $response->body());
        }

        return trim($response->body(), '"');
    }

    public function completeUpdateSession(string $sessionId): void
    {
        $this->client()->post("{$this->baseUrl}/content/library/item/update-session/{$sessionId}?action=complete");
    }

    // Admin dropdowns

    /** @return array<array{id: string, name: string}> */
    public function listFolders(): array
    {
        $response = $this->client()->get("{$this->baseUrl}/vcenter/folder");

        if (!$response->successful()) {
            return [];
        }

        return collect($response->json() ?? [])
            ->filter(fn ($f) => ($f['type'] ?? '') === 'VIRTUAL_MACHINE')
            ->map(fn ($f) => ['id' => $f['folder'], 'name' => $f['name']])
            ->values()
            ->all();
    }

    /** @return array<array{id: string, name: string}> */
    public function listTemplates(): array
    {
        $response = $this->client()->get("{$this->baseUrl}/vcenter/vm", [
            'filter.power_states' => ['POWERED_OFF'],
        ]);

        if (!$response->successful()) {
            return [];
        }

        return collect($response->json() ?? [])
            ->map(fn ($vm) => ['id' => $vm['vm'], 'name' => $vm['name']])
            ->values()
            ->all();
    }

    /** @return array<array{id: string, name: string}> */
    public function listDatastores(): array
    {
        $response = $this->client()->get("{$this->baseUrl}/vcenter/datastore");

        if (!$response->successful()) {
            return [];
        }

        return collect($response->json() ?? [])
            ->map(fn ($ds) => ['id' => $ds['datastore'], 'name' => $ds['name']])
            ->values()
            ->all();
    }

    /** @return array<array{id: string, name: string}> */
    public function listClusters(): array
    {
        $response = $this->client()->get("{$this->baseUrl}/vcenter/cluster");

        if (!$response->successful()) {
            return [];
        }

        return collect($response->json() ?? [])
            ->map(fn ($c) => ['id' => $c['cluster'], 'name' => $c['name']])
            ->values()
            ->all();
    }

    /** @return array<array{id: string, name: string}> */
    public function listHosts(): array
    {
        $response = $this->client()->get("{$this->baseUrl}/vcenter/host");

        if (!$response->successful()) {
            return [];
        }

        return collect($response->json() ?? [])
            ->map(fn ($h) => ['id' => $h['host'], 'name' => $h['name']])
            ->values()
            ->all();
    }
}

<?php

namespace Fywolf\VcenterVps\Billing;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The only place in this plugin that knows billing is a remote service.
 *
 * Everything else talks to models, DTOs and local columns. If billing's API
 * changes shape, or moves again, this is the file that changes.
 *
 * Note what is *not* here: no order lookup, no customer lookup, no entitlement
 * check. Those all used to be reads across the in-panel relation, and turning
 * them into synchronous HTTP calls would put billing on the critical path of
 * every console open and every VPS list render. Instead billing pushes those
 * facts to the plugin when they change, and the plugin reads its own columns.
 * The calls that remain are admin-time conveniences and best-effort telemetry.
 */
class BillingClient
{
    private const PACKS_CACHE_KEY = 'vcenter-vps.billing.packs';

    private const PACKS_CACHE_TTL = 300;

    public function isConfigured(): bool
    {
        return filled($this->baseUrl()) && filled(config('vcenter-vps.billing.token'));
    }

    /**
     * Packs available to attach vCenter settings to, as `id => name`.
     *
     * Cached because it backs a Filament select that re-renders often, and
     * degrades to an empty list rather than throwing: an admin editing pack
     * settings while billing is down should see an empty dropdown and a hint,
     * not a 500.
     *
     * @return array<int, string>
     */
    public function packs(): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        return Cache::remember(self::PACKS_CACHE_KEY, self::PACKS_CACHE_TTL, function (): array {
            try {
                $response = $this->request()->get('/api/packs');

                if ($response->failed()) {
                    Log::warning('vcenter-vps: billing pack lookup failed', [
                        'status' => $response->status(),
                    ]);

                    return [];
                }

                return collect($response->json('data', []))
                    ->pluck('name', 'id')
                    ->map(fn ($name) => (string) $name)
                    ->all();
            } catch (Throwable $e) {
                Log::warning('vcenter-vps: billing unreachable for pack lookup', [
                    'error' => $e->getMessage(),
                ]);

                return [];
            }
        });
    }

    public function forgetCachedPacks(): void
    {
        Cache::forget(self::PACKS_CACHE_KEY);
    }

    /**
     * Best-effort audit push. Never throws — an audit trail that can fail a
     * provision is worse than a missing audit line, and the event is written to
     * the panel log regardless of whether billing accepts it.
     *
     * @param  array<string, mixed>  $context
     */
    public function recordAudit(string $event, array $context, ?int $orderId = null): void
    {
        Log::info("vcenter-vps: {$event}", $context + ['billing_order_id' => $orderId]);

        if (! $this->isConfigured()) {
            return;
        }

        try {
            $this->request()->post('/api/audit-logs', [
                'event'    => $event,
                'context'  => $context,
                'order_id' => $orderId,
                'source'   => 'vcenter-vps',
            ]);
        } catch (Throwable $e) {
            Log::warning('vcenter-vps: audit push failed', [
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Deep link to an order in billing's admin, replacing the old in-process
     * `OrderResource::getUrl()`. Returns null when billing is unconfigured so
     * callers can render plain text instead of a dead link.
     */
    public function orderUrl(?int $orderId): ?string
    {
        if (! $orderId || blank($this->baseUrl())) {
            return null;
        }

        return $this->baseUrl() . '/admin/orders?tableSearch=' . $orderId;
    }

    private function request(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->withToken((string) config('vcenter-vps.billing.token'))
            ->acceptJson()
            ->timeout((int) config('vcenter-vps.billing.timeout', 10));
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('vcenter-vps.billing.url'), '/');
    }
}

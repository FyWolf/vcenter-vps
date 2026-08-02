<?php

namespace Fywolf\VcenterVps\Billing\Data;

use Illuminate\Support\Carbon;

/**
 * What billing tells the plugin about an order.
 *
 * This replaces `Fywolf\Billing\Models\Order`. It is a value object, not an
 * Eloquent model: the plugin cannot query billing's tables any more, so an order
 * only exists here as the payload of a request billing made. Anything the plugin
 * needs to keep is copied onto `vps_instances` at that moment.
 *
 * `userId` is the *panel* user id, not billing's customer id. Billing owns the
 * customer↔panel-user mapping through the OAuth link it already has; resolving it
 * there and sending the panel id means the plugin never needs a customer concept
 * of its own, and console authorization stays a local integer comparison.
 */
readonly class OrderData
{
    public function __construct(
        public int $orderId,
        public int $packId,
        public ?string $packName,
        public int $userId,
        public ?string $customerLabel,
        public ?string $status,
        public ?Carbon $expiresAt,
        public ?int $cores,
        public ?int $memoryMb,
        public ?int $diskGb,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            orderId: (int) $payload['order_id'],
            packId: (int) $payload['pack_id'],
            packName: $payload['pack_name'] ?? null,
            userId: (int) $payload['user_id'],
            customerLabel: $payload['customer_label'] ?? null,
            status: $payload['status'] ?? null,
            expiresAt: isset($payload['expires_at']) ? Carbon::parse($payload['expires_at']) : null,
            // Per-order overrides from the purchased price tier. Null means "use
            // the pack's configured default", which is how the clone path has
            // always behaved.
            cores: isset($payload['cores']) ? (int) $payload['cores'] : null,
            memoryMb: isset($payload['memory_mb']) ? (int) $payload['memory_mb'] : null,
            diskGb: isset($payload['disk_gb']) ? (int) $payload['disk_gb'] : null,
        );
    }

    /**
     * The columns copied onto `vps_instances` so the plugin can render and
     * authorize without reaching billing.
     *
     * @return array<string, mixed>
     */
    public function toInstanceColumns(): array
    {
        return [
            'billing_order_id' => $this->orderId,
            'billing_pack_id'  => $this->packId,
            'user_id'          => $this->userId,
            'pack_name'        => $this->packName,
            'customer_label'   => $this->customerLabel,
            'order_status'     => $this->status,
            'order_expires_at' => $this->expiresAt,
            'spec_cores'       => $this->cores,
            'spec_memory_mb'   => $this->memoryMb,
            'spec_disk_gb'     => $this->diskGb,
        ];
    }
}

<?php

namespace Fywolf\VcenterVps\Models;

use Fywolf\VcenterVps\Billing\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $billing_order_id
 * @property ?int $user_id
 * @property ?int $billing_pack_id
 * @property ?string $pack_name
 * @property ?string $customer_label
 * @property ?string $order_status
 * @property ?Carbon $order_expires_at
 * @property ?int $spec_cores
 * @property ?int $spec_memory_mb
 * @property ?int $spec_disk_gb
 * @property ?string $name
 * @property string $vm_id
 * @property ?string $vm_ip
 * @property ?string $state_cache
 * @property ?string $install_status  null (clone-based) | 'pending' | 'complete'
 * @property ?string $iso_item_id
 * @property ?string $cdrom_id
 * @property ?Carbon $state_checked_at
 */
class VpsInstance extends Model
{
    public const INSTALL_PENDING  = 'pending';
    public const INSTALL_COMPLETE = 'complete';

    protected $table = 'vps_instances';

    protected $fillable = [
        'billing_order_id',
        'user_id',
        'billing_pack_id',
        'pack_name',
        'customer_label',
        'order_status',
        'order_expires_at',
        'spec_cores',
        'spec_memory_mb',
        'spec_disk_gb',
        'name',
        'vm_id',
        'vm_ip',
        'state_cache',
        'install_status',
        'iso_item_id',
        'cdrom_id',
        'state_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'state_checked_at' => 'datetime',
            'order_expires_at' => 'datetime',
        ];
    }

    /**
     * Instances a given panel user may see and control.
     *
     * This is the whole authorization story now, and it is entirely local. It
     * used to be `whereHas('order', fn ($q) => $q->where('customer_id', ...))`,
     * which needed billing's tables in this database. Keeping it local means a
     * billing outage does not lock customers out of their own machines.
     */
    public function scopeOwnedBy(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId)
            ->whereIn('order_status', OrderStatus::listable());
    }

    public function isRunning(): bool
    {
        return $this->state_cache === 'POWERED_ON';
    }

    public function isStopped(): bool
    {
        return $this->state_cache === 'POWERED_OFF';
    }

    public function isAwaitingInstall(): bool
    {
        return $this->install_status === self::INSTALL_PENDING;
    }

    public function isReady(): bool
    {
        return $this->install_status === null || $this->install_status === self::INSTALL_COMPLETE;
    }

    /**
     * Billing's status as an enum, or null if it is one this plugin does not
     * model. Callers must handle null — see the note on OrderStatus.
     */
    public function orderStatus(): ?OrderStatus
    {
        return $this->order_status ? OrderStatus::tryFrom($this->order_status) : null;
    }

    public function getFilamentName(): string
    {
        return $this->name ?? $this->pack_name ?? 'VPS #' . $this->id;
    }
}

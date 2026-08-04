<?php

namespace Fywolf\VcenterVps\Models;

use Fywolf\VcenterVps\Billing\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        return $query->where('user_id', $userId)->whereLive();
    }

    /**
     * The order is in a state where the customer should still see the machine.
     *
     * Split out of `ownedBy()` so `accessibleBy()` applies exactly the same rule
     * — a collaborator must not keep seeing an instance whose order has lapsed
     * when the owner no longer does.
     */
    public function scopeWhereLive(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q
            // A null status means billing has not synced this instance yet,
            // not that the order is dead. Hiding on null makes a missed sync
            // look to the customer like their machine was taken away, which
            // is worse than briefly showing one whose order has lapsed — the
            // VM's own power state already limits that case.
            ->whereNull('order_status')
            ->orWhereIn('order_status', OrderStatus::listable()));
    }

    /**
     * Instances a user may reach at all: their own, plus those shared with them.
     *
     * **This is not the same question as `ownedBy()` and the two must stay
     * apart.** Reaching a machine is one thing; reinstalling it or changing its
     * boot media is another, and those pages check ownership directly. Widening
     * `ownedBy()` instead of adding this would have quietly handed collaborators
     * the destructive half.
     */
    public function scopeAccessibleBy(Builder $query, int $userId): Builder
    {
        return $query
            ->where(fn (Builder $q) => $q
                ->where('user_id', $userId)
                ->orWhereHas('collaborators', fn (Builder $c) => $c->where('user_id', $userId)))
            ->whereLive();
    }

    /** People this instance has been shared with, written only by billing. */
    public function collaborators(): HasMany
    {
        return $this->hasMany(VpsInstanceUser::class, 'vps_instance_id');
    }

    public function isOwnedBy(?int $userId): bool
    {
        return $userId !== null && $this->user_id !== null && $this->user_id === $userId;
    }

    /**
     * May this user do this specific thing?
     *
     * The owner may do everything; a collaborator only what they were granted.
     * Called per action rather than once per page, because a collaborator with
     * console access but no power rights is an ordinary thing for an owner to
     * want and the two buttons sit side by side.
     */
    public function userCan(?int $userId, string $permission): bool
    {
        if ($userId === null) {
            return false;
        }

        if ($this->isOwnedBy($userId)) {
            return true;
        }

        $collaborator = $this->collaborators->firstWhere('user_id', $userId);

        return $collaborator !== null
            && in_array($permission, $collaborator->permissions ?? [], true);
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

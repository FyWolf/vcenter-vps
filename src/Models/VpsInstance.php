<?php

namespace Fywolf\VcenterVps\Models;

use Fywolf\Billing\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $order_id
 * @property ?string $name
 * @property string $vm_id
 * @property ?string $vm_ip
 * @property ?string $state_cache
 * @property ?string $install_status  null (clone-based) | 'pending' | 'complete'
 * @property ?string $iso_item_id
 * @property ?string $cdrom_id
 * @property ?Carbon $state_checked_at
 * @property Order $order
 */
class VpsInstance extends Model
{
    public const INSTALL_PENDING  = 'pending';
    public const INSTALL_COMPLETE = 'complete';

    protected $table = 'vps_instances';

    protected $fillable = [
        'order_id',
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
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
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
}

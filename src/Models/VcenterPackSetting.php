<?php

namespace Fywolf\VcenterVps\Models;

use Fywolf\Billing\Models\Pack;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $pack_id
 * @property string $provision_type  'clone' | 'iso'
 * @property string $guest_os_id
 * @property ?string $default_iso_item_id
 * @property ?string $template_id
 * @property ?string $folder_id
 * @property string $datastore_id
 * @property string $cluster_id
 * @property ?string $network_id
 * @property int $default_cpu
 * @property int $default_memory_mb
 * @property int $default_disk_gb
 * @property Pack $pack
 */
class VcenterPackSetting extends Model
{
    protected $table = 'vcenter_pack_settings';

    protected $fillable = [
        'pack_id',
        'provision_type',
        'placement_type',
        'guest_os_id',
        'default_iso_item_id',
        'template_id',
        'folder_id',
        'datastore_id',
        'cluster_id',
        'network_id',
        'default_cpu',
        'default_memory_mb',
        'default_disk_gb',
    ];

    public function pack(): BelongsTo
    {
        return $this->belongsTo(Pack::class, 'pack_id');
    }

    public function isIsoProvision(): bool
    {
        return $this->provision_type === 'iso';
    }

    public function isCloneProvision(): bool
    {
        return $this->provision_type === 'clone';
    }
}

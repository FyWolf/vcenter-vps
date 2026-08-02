<?php

namespace Fywolf\VcenterVps\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $billing_pack_id
 * @property ?string $pack_name
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
 */
class VcenterPackSetting extends Model
{
    protected $table = 'vcenter_pack_settings';

    protected $fillable = [
        'billing_pack_id',
        'pack_name',
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

    /**
     * `pack_name` is a copy of billing's pack name, refreshed whenever billing
     * sends one. It exists so the admin list renders without a round trip; the
     * pack itself is billing's, and `billing_pack_id` is the only identifier
     * that matters when the two sides talk.
     */
    public function isIsoProvision(): bool
    {
        return $this->provision_type === 'iso';
    }

    public function isCloneProvision(): bool
    {
        return $this->provision_type === 'clone';
    }
}

<?php

namespace Fywolf\VcenterVps\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Someone a VPS has been shared with.
 *
 * Written only by the billing service through the plugin's API. The panel offers
 * no screen for adding one, which is the point: billing records who was invited,
 * with what, and revokes on termination, so there is exactly one writer and
 * nothing to reconcile.
 *
 * @property int $vps_instance_id
 * @property int $user_id
 * @property string[] $permissions
 */
class VpsInstanceUser extends Model
{
    /** Open the remote console. */
    public const CONSOLE = 'console';

    /** Start, stop and restart. */
    public const POWER = 'power';

    /** Rename it and change its display settings. */
    public const SETTINGS = 'settings';

    protected $table = 'vps_instance_users';

    protected $fillable = ['vps_instance_id', 'user_id', 'permissions'];

    protected function casts(): array
    {
        return ['permissions' => 'array'];
    }

    /**
     * Everything an owner may hand out.
     *
     * Reinstalling and changing boot media are deliberately absent: they destroy
     * the machine's contents, and the person paying for it should be the only
     * one who can. `BootVps` and `SettingsVps` check ownership directly rather
     * than trusting this list, so adding a case here would not by itself open
     * them.
     *
     * @return list<string>
     */
    public static function grantable(): array
    {
        return [self::CONSOLE, self::POWER, self::SETTINGS];
    }

    /**
     * Drop anything unknown.
     *
     * Applied to whatever arrives over the API, because the billing service is a
     * separate deployment and may be a release ahead — an unrecognised
     * permission must be ignored, not stored where a later version of this
     * plugin might interpret it.
     *
     * @param  array<int, mixed>  $permissions
     * @return list<string>
     */
    public static function clean(array $permissions): array
    {
        return array_values(array_unique(array_filter(
            array_map(fn ($permission) => is_string($permission) ? $permission : null, $permissions),
            fn (?string $permission) => $permission !== null
                && in_array($permission, self::grantable(), true),
        )));
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(VpsInstance::class, 'vps_instance_id');
    }
}

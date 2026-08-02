<?php

namespace Fywolf\VcenterVps\Billing\Enums;

/**
 * A local copy of the statuses this plugin actually gates on.
 *
 * This is not a mirror of billing's enum and should not become one. Billing owns
 * the full lifecycle; the plugin only needs to answer "should this VPS still be
 * listed for the customer?", which is these three cases. `vps_instances.order_status`
 * stores billing's raw string, so a status this enum has never heard of is stored
 * and displayed fine — it simply falls outside `listable()` and the VPS stops
 * appearing in the customer list, which is the safe direction to fail.
 *
 * @todo Confirm the backed values against the billing app before first deploy.
 *       They are carried over from the in-panel plugin's enum; if billing has
 *       since renamed any of them, only the strings here need to change.
 */
enum OrderStatus: string
{
    case Active = 'active';
    case GracePeriod = 'grace_period';
    case Cancelled = 'cancelled';

    /**
     * Statuses whose VPS the customer may still see and control.
     *
     * Cancelled is included on purpose: a cancelled order runs to the end of its
     * paid term, and locking the customer out the moment they click cancel would
     * strand data on a machine they have already paid for.
     *
     * @return string[]
     */
    public static function listable(): array
    {
        return array_map(
            fn (self $status) => $status->value,
            [self::Active, self::GracePeriod, self::Cancelled],
        );
    }

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::GracePeriod => 'Grace period',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::GracePeriod => 'warning',
            self::Cancelled => 'danger',
        };
    }
}

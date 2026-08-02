<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Billing no longer lives in the panel database.
 *
 * `billing_orders` and `billing_packs` are gone from this database, so the two
 * foreign keys that pointed at them cannot survive, and neither can the Eloquent
 * relations that read through them. Everything the plugin used to fetch across
 * that relation — who owns the VPS, what pack it is, whether the order is still
 * active — is denormalised onto the local tables instead.
 *
 * That is deliberate rather than merely convenient. Console access and the "My
 * VPS" list authorize off these columns, so a customer can still reach their
 * machine when the billing app is unreachable. Calling billing on every console
 * request would make a billing outage into a datacenter outage.
 *
 * The cached billing facts (`order_status`, `order_expires_at`, `customer_label`,
 * `pack_name`) are refreshed by billing pushing to the plugin's API, not by the
 * plugin polling. They are display and gating values, never the source of truth.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Fresh installs never created these FKs (001/002 no longer add them);
        // installs from before the billing split still have them.
        $this->dropForeignKeyIfExists('vps_instances', 'order_id');
        $this->dropForeignKeyIfExists('vcenter_pack_settings', 'pack_id');

        Schema::table('vps_instances', function (Blueprint $table) {
            $table->renameColumn('order_id', 'billing_order_id');
        });

        Schema::table('vps_instances', function (Blueprint $table) {
            // No FK to `users`: the panel owns that table and its key type has
            // changed across panel majors before. An index is enough here.
            $table->unsignedInteger('user_id')->nullable()->after('billing_order_id')->index();
            $table->unsignedInteger('billing_pack_id')->nullable()->after('user_id')->index();
            $table->string('pack_name')->nullable()->after('billing_pack_id');
            $table->string('customer_label')->nullable()->after('pack_name');

            // Billing's raw status string, not a constrained enum column — an
            // unrecognised status from a future billing release should show up
            // in the admin table, not break the insert.
            $table->string('order_status')->nullable()->after('customer_label')->index();
            $table->timestamp('order_expires_at')->nullable()->after('order_status');

            // The spec the customer actually bought, which the VPS detail pages
            // display. It used to be read live off `order->packPrice`. Copying it
            // is also more correct than reading it live would have been: a price
            // tier edited in billing later must not retroactively change what an
            // existing machine claims to be.
            $table->unsignedInteger('spec_cores')->nullable()->after('order_expires_at');
            $table->unsignedInteger('spec_memory_mb')->nullable()->after('spec_cores');
            $table->unsignedInteger('spec_disk_gb')->nullable()->after('spec_memory_mb');
        });

        Schema::table('vcenter_pack_settings', function (Blueprint $table) {
            $table->renameColumn('pack_id', 'billing_pack_id');
        });

        Schema::table('vcenter_pack_settings', function (Blueprint $table) {
            $table->string('pack_name')->nullable()->after('billing_pack_id');
        });
    }

    public function down(): void
    {
        Schema::table('vps_instances', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['billing_pack_id']);
            $table->dropIndex(['order_status']);
            $table->dropColumn([
                'user_id',
                'billing_pack_id',
                'pack_name',
                'customer_label',
                'order_status',
                'order_expires_at',
                'spec_cores',
                'spec_memory_mb',
                'spec_disk_gb',
            ]);
        });

        Schema::table('vps_instances', function (Blueprint $table) {
            $table->renameColumn('billing_order_id', 'order_id');
        });

        Schema::table('vcenter_pack_settings', function (Blueprint $table) {
            $table->dropColumn('pack_name');
        });

        Schema::table('vcenter_pack_settings', function (Blueprint $table) {
            $table->renameColumn('billing_pack_id', 'pack_id');
        });

        // The foreign keys are not restored: the tables they referenced do not
        // exist in this database any more, so re-adding them would fail.
    }

    private function dropForeignKeyIfExists(string $table, string $column): void
    {
        $exists = collect(Schema::getForeignKeys($table))
            ->contains(fn (array $key) => in_array($column, $key['columns'], true));

        if (! $exists) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column) {
            $blueprint->dropForeign([$column]);
        });
    }
};

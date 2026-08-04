<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * People a VPS has been shared with.
 *
 * A game server has Pelican's own `subusers` table; a VPS had nothing — the
 * instance carried a single `user_id` and every check compared against it, so
 * an owner who wanted to give a friend console access had to hand over their
 * password.
 *
 * Kept deliberately small. A VPS exposes far less than a game server: the
 * console, the power buttons, and the settings page. So this stores a short list
 * of permission strings rather than mirroring Pelican's 44-value enum, and the
 * genuinely destructive pages — reinstall and boot media — are **not grantable
 * at all**, they stay with the owner.
 *
 * **Billing is the only writer**, exactly as for game-server collaborators: the
 * storefront records who was invited, with what, and revokes on termination. The
 * panel offers no UI to add a row here, so there is nothing to reconcile. Rows
 * arrive through the plugin's API from the billing service.
 *
 * `user_id` is a plain column with no foreign key to `users`, matching the rest
 * of this plugin after the billing split: authorization must keep working from
 * local columns even when other systems are unreachable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vps_instance_users', function (Blueprint $table) {
            $table->id();

            $table->foreignId('vps_instance_id')
                ->constrained('vps_instances')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('user_id');
            $table->json('permissions');

            $table->timestamps();

            // One row per person per instance. Billing re-sends a grant to change
            // what somebody can do, so this is an upsert key, not a guard against
            // a mistake.
            $table->unique(['vps_instance_id', 'user_id']);

            // "Which machines can this person reach" is the query behind the
            // whole VPS list.
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vps_instance_users');
    }
};

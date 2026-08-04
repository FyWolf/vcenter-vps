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
 *
 * **Every key here is a 4-byte `int unsigned`, not Laravel's default `bigint`.**
 * `vps_instances.id` is `increments()`, as is every other table in this plugin
 * and in Pelican itself, and MariaDB refuses a foreign key whose column is a
 * different width from the one it references — errno 150, "Foreign key
 * constraint is incorrectly formed", which names neither column. So `id()`,
 * `foreignId()` and `unsignedBigInteger()` are all wrong in this codebase; use
 * `increments()` and `unsignedInteger()`.
 */
return new class extends Migration
{
    public function up(): void
    {
        // The first release of this migration used `foreignId()` and failed on
        // the constraint *after* MariaDB had already created the table, leaving
        // it behind un-recorded — so a fixed re-run would hit "table already
        // exists" and never get past it. Dropping first is safe: a table that
        // was never successfully migrated cannot hold a row anyone wrote.
        Schema::dropIfExists('vps_instance_users');

        Schema::create('vps_instance_users', function (Blueprint $table) {
            $table->increments('id');

            $table->unsignedInteger('vps_instance_id');
            $table->foreign('vps_instance_id')
                ->references('id')->on('vps_instances')
                ->cascadeOnDelete();

            $table->unsignedInteger('user_id');
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

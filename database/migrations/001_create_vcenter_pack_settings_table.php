<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vcenter_pack_settings', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('pack_id')->unique();
            $table->string('provision_type')->default('clone');
            $table->string('placement_type')->default('cluster');
            $table->string('template_id')->nullable();
            $table->string('guest_os_id')->nullable();
            $table->string('default_iso_item_id')->nullable();
            $table->string('folder_id')->nullable();
            $table->string('datastore_id');
            $table->string('cluster_id');
            $table->unsignedInteger('default_cpu')->default(2);
            $table->unsignedInteger('default_memory_mb')->default(2048);
            $table->unsignedInteger('default_disk_gb')->default(20);
            $table->timestamps();

            $table->foreign('pack_id')->references('id')->on('billing_packs')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vcenter_pack_settings');
    }
};

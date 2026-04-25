<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vps_instances', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('order_id')->unique();
            $table->string('vm_id');
            $table->string('vm_ip')->nullable();
            $table->string('state_cache')->nullable();
            $table->timestamp('state_checked_at')->nullable();
            $table->string('install_status')->nullable();
            $table->string('iso_item_id')->nullable();
            $table->string('cdrom_id')->nullable();
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('billing_orders')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vps_instances');
    }
};

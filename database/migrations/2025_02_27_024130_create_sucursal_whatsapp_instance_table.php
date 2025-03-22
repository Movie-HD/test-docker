<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sucursal_whatsapp_instance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_instance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sucursal_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['sucursal_id', 'whatsapp_instance_id'], 'suc_whatsapp_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sucursal_whatsapp_instance');
    }
};

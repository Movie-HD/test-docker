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
        Schema::create('whatsapp_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizacion_id')->constrained()->cascadeOnDelete();
            $table->string('phone_number')->unique();
            $table->string('instance_name')->nullable();
            $table->text('qr_code')->nullable();
            $table->enum('status', ['pending', 'connected', 'disconnected', 'qr_expired'])->default('disconnected');
            $table->timestamp('qr_generated_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_instances');
    }
};

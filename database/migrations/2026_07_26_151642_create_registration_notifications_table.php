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
        Schema::create('registration_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained()->cascadeOnDelete();
            $table->string('tipo', 30); // pendiente_creada|pago_confirmado|recordatorio_30|recordatorio_15|reversion_cupo|recordatorio_kit
            $table->string('canal', 20); // email|whatsapp_openwa|whatsapp_externo
            $table->timestamp('enviado_at');
            $table->unique(['registration_id', 'tipo', 'canal']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registration_notifications');
    }
};

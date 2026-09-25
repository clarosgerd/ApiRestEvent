<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SmartStand fase 4 (26/09/2026) — asistentes que pidieron no recibir más
 * correos de seguimiento de expositores (link de baja de cada correo). Vale
 * para TODAS las empresas del sistema. Se guarda el correo en minúsculas, no
 * el participante: la baja debe sobrevivir a que se borre o se re-inscriba.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seguimiento_bajas', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seguimiento_bajas');
    }
};

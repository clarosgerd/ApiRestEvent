<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SmartStand fase 4 (26/09/2026) — estado del correo de seguimiento de cada
 * lead: `enviado` | `fallido` | `omitido` (+ motivo: baja, tope, sin_correo,
 * error). `seguimiento_at` es DATETIME (no TIMESTAMP, ver create_leads_capturados).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads_capturados', function (Blueprint $table) {
            $table->string('seguimiento_estado', 20)->nullable()->after('capturado_at');
            $table->string('seguimiento_motivo', 20)->nullable()->after('seguimiento_estado');
            $table->unsignedTinyInteger('seguimiento_intentos')->default(0)->after('seguimiento_motivo');
            $table->dateTime('seguimiento_at')->nullable()->after('seguimiento_intentos');
        });
    }

    public function down(): void
    {
        Schema::table('leads_capturados', function (Blueprint $table) {
            $table->dropColumn(['seguimiento_estado', 'seguimiento_motivo', 'seguimiento_intentos', 'seguimiento_at']);
        });
    }
};

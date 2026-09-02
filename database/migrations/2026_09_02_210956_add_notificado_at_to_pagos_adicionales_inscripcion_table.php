<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Correo de confirmación por pago adicional (02/09/2026) — hueco real
 * encontrado durante el incidente en UAT: `ConfirmarPagoAdicionalAction`
 * nunca disparaba ningún correo, ni siquiera cuando el pago se aplicaba
 * bien. `registration_notifications` (registration_id+tipo+canal único)
 * no sirve acá tal cual — un mismo registro puede tener varios pagos
 * adicionales a lo largo del tiempo (talleres agregados en distintas
 * ediciones), y ese UNIQUE bloquearía el segundo. Se rastrea la
 * idempotencia por PAGO adicional, no por inscripción — cada fila de
 * `pagos_adicionales_inscripcion` ya es 1:1 con "un cobro adicional
 * puntual", que es exactamente lo que se quiere notificar una sola vez.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagos_adicionales_inscripcion', function (Blueprint $table) {
            $table->timestamp('notificado_at')->nullable()->after('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('pagos_adicionales_inscripcion', function (Blueprint $table) {
            $table->dropColumn('notificado_at');
        });
    }
};

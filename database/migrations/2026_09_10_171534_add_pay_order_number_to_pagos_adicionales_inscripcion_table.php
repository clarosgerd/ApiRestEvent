<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cobro adicional real por Multipago (10/09/2026) — ver
 * brain/api_rest_event/PLAN-SIP-BANCO-SEGURO-MULTIPAGO-ADICIONAL-10092026.md.
 * Antes solo existía `qr_id` (SIP, nullable, solo diagnóstico — nunca se
 * usa para lookup, ver su propio comentario en la migración original).
 * `pay_order_number` SÍ es la clave real de lookup para Multipago —
 * equivalente a `registrations.pay_order_number`, usado por el webhook
 * (payment_callback_multipago.php) y por el poll activo
 * (pago_status_adicional.php, getPayOrderByNumber()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagos_adicionales_inscripcion', function (Blueprint $table) {
            $table->string('pay_order_number')->nullable()->after('qr_id');
        });
    }

    public function down(): void
    {
        Schema::table('pagos_adicionales_inscripcion', function (Blueprint $table) {
            $table->dropColumn('pay_order_number');
        });
    }
};

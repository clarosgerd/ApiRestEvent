<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Pagar en el evento (efectivo)" al agregar un taller a una inscripción
 * pagada — configurable por evento (02/09/2026). Ver
 * PLAN-COBRO-SIP-ADICIONAL-26082026.md para el contexto original: cuando
 * se agregó el cobro real por SIP del monto adicional, se ofreció "junto a
 * la opción de siempre (pagar en efectivo en el evento), nunca la
 * reemplaza" — decisión explícita de ese momento. Ahora el organizador
 * pide poder sacar la opción de efectivo y dejar QR como única forma de
 * pagar el adicional.
 *
 * Default `false` a propósito: ningún evento existente cambia de
 * comportamiento (siguen viéndose ambas opciones); el organizador lo
 * prende puntualmente desde admin-eventos. Mismo patrón que
 * `fee_incluye_talleres`/`usd_precio_fijo`.
 *
 * No depende de si SIP está configurado — pago_adicional.php ya cae a un
 * QR simulado cuando no hay pasarela real (mismo criterio que
 * registro.php), así que forzar QR nunca deja al participante sin forma
 * de completar el pago.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->boolean('forzar_qr_pago_adicional')->default(false)->after('usd_precio_fijo');
        });
    }

    public function down(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->dropColumn('forzar_qr_pago_adicional');
        });
    }
};

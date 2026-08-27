<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reporte de talleres confiable (27/08/2026) — ver
 * PLAN-COBRO-SIP-ADICIONAL-26082026.md. Antes de esto no había forma de
 * distinguir, en una fila de `participante_taller_sesion`, si ese taller ya
 * fue cobrado (parte de la inscripción original, cobrado en Caja al
 * momento, o confirmado por SIP) o si el participante eligió "pagar en el
 * evento" al agregarlo desde su cuenta (autoservicio) y todavía no se
 * cobró — el reporte de talleres mezclaba las dos cosas bajo "recaudación"
 * sin ninguna señal de cuál era cuál.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participante_taller_sesion', function (Blueprint $table) {
            $table->boolean('pago_pendiente')->default(false)->after('total');
        });
    }

    public function down(): void
    {
        Schema::table('participante_taller_sesion', function (Blueprint $table) {
            $table->dropColumn('pago_pendiente');
        });
    }
};

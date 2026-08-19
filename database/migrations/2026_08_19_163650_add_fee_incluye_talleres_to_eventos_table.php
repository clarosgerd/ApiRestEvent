<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cargo de servicio sobre talleres, configurable por evento (19/08/2026) —
 * pedido: "necesito una opción que no apliquemos el fee a los talleres".
 *
 * Contexto: el mismo día se cambió la base del cargo de servicio de "solo
 * inscripción" a "inscripción + talleres" (SIP/Multipago cobran su
 * comisión sobre el monto total procesado). El usuario ahora pide poder
 * desactivar eso por evento — no volver al comportamiento global viejo.
 *
 * Default `true` a propósito: mantiene el comportamiento que se acaba de
 * confirmar (inscripción + talleres) para todo evento existente y nuevo,
 * y cada organizador/super_admin puede apagarlo puntualmente desde
 * admin-eventos si su convenio de gateway no lo justifica.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->boolean('fee_incluye_talleres')->default(true)->after('talleres_con_costo');
        });
    }

    public function down(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->dropColumn('fee_incluye_talleres');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inscripción en BOB y USD (18/08/2026) — ver
 * brain/PLAN-INSCRIPCION-BOB-USD-IMPLEMENTACION.md. Bandera configurable
 * por evento para indicar si el organizador acepta pago en USD (para
 * extranjeros). Default false: eventos existentes siguen aceptando solo
 * BOB sin cambio de comportamiento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->boolean('acepta_usd')->default(false)->after('talleres_con_costo');
        });
    }

    public function down(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->dropColumn('acepta_usd');
        });
    }
};
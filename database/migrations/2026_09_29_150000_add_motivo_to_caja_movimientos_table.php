<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Caja: quitar/cambiar un taller ya pagado con devolución (29/09/2026) —
 * ver brain/PLAN-CAJA-QUITAR-TALLER-PAGADO-29092026.md. Motivo obligatorio
 * (exigido por la Action, no acá) cuando el movimiento incluye quitar un
 * taller ya cobrado — para el cierre de turno. Aditiva pura: nullable,
 * ningún movimiento existente cambia de comportamiento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('caja_movimientos', function (Blueprint $table) {
            $table->text('motivo')->nullable()->after('metodo_pago');
        });
    }

    public function down(): void
    {
        Schema::table('caja_movimientos', function (Blueprint $table) {
            $table->dropColumn('motivo');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Observaciones en Caja (07/10/2026) — pedido real del organizador: una
 * nota libre y opcional por cobro, disponible para CUALQUIER forma de pago
 * (Efectivo/QR/Depósito/Organizador/Cortesía), a diferencia de `motivo`
 * (29/09/2026), que es específico de quitar/cambiar un taller ya pagado y
 * obligatorio solo en ese caso. Aditiva pura: nullable, ningún movimiento
 * existente cambia de comportamiento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('caja_movimientos', function (Blueprint $table) {
            $table->text('observaciones')->nullable()->after('motivo');
        });
    }

    public function down(): void
    {
        Schema::table('caja_movimientos', function (Blueprint $table) {
            $table->dropColumn('observaciones');
        });
    }
};

<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

/**
 * Reserva de cupo con seña (21/10/2026) — nuevo tipo de movimiento para
 * CajaController::cobrarSaldo(). `tipo` es un ENUM real a nivel MySQL —
 * mismo ALTER TABLE MODIFY que ya usó add_anulacion_a_caja_movimientos_table
 * (Schema::table()->enum() no permite modificar uno existente).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE caja_movimientos MODIFY tipo ENUM('inscripcion_nueva', 'cobro_pendiente', 'edicion_pagada', 'anulacion', 'cobro_saldo') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE caja_movimientos MODIFY tipo ENUM('inscripcion_nueva', 'cobro_pendiente', 'edicion_pagada', 'anulacion') NOT NULL");
    }
};

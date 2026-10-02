<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Caja: anular un cobro ya registrado (02/10/2026) — el cajero puede marcar
 * que un cobro que ya entró se devolvió después por un motivo ajeno al
 * sistema (chargeback bancario, devolución manual). Ver AnularCobroAction.
 *
 * `tipo` es un ENUM real a nivel MySQL — Schema::table()->enum() no permite
 * modificar uno existente, hace falta DB::statement con ALTER TABLE MODIFY.
 *
 * `anula_movimiento_id` apunta, en el movimiento de anulación, al
 * movimiento original que revierte — sirve para trazabilidad y para
 * detectar si un movimiento ya fue anulado antes (no se puede anular dos
 * veces lo mismo). Self-referencing, por eso se agrega en un
 * Schema::table() aparte (no se puede declarar en el create() original con
 * foreignId apuntando a la misma tabla que se está creando).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE caja_movimientos MODIFY tipo ENUM('inscripcion_nueva', 'cobro_pendiente', 'edicion_pagada', 'anulacion') NOT NULL");

        Schema::table('caja_movimientos', function (Blueprint $table) {
            $table->foreignId('anula_movimiento_id')->nullable()->after('motivo')
                ->constrained('caja_movimientos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('caja_movimientos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('anula_movimiento_id');
        });

        DB::statement("ALTER TABLE caja_movimientos MODIFY tipo ENUM('inscripcion_nueva', 'cobro_pendiente', 'edicion_pagada') NOT NULL");
    }
};

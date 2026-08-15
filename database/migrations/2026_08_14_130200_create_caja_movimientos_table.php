<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Caja de cobro presencial (14/08/2026) — ver
 * PLAN-CAJA-COBRO-PRESENCIAL-14082026.md. Auditoría real de dinero
 * cobrado en caja — separada de AuditLog (que hoy solo audita
 * costo_adicion de edición, no es un libro de caja). Todo movimiento
 * pertenece a un turno (caja_turno_id obligatorio), nunca queda suelto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('caja_movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caja_turno_id')->constrained('caja_turnos')->cascadeOnDelete();
            $table->foreignId('evento_id')->constrained('eventos')->cascadeOnDelete();
            $table->foreignId('registration_id')->constrained('registrations')->cascadeOnDelete();
            $table->foreignId('admin_user_id')->constrained('admin_users')->cascadeOnDelete();
            $table->enum('tipo', ['inscripcion_nueva', 'cobro_pendiente', 'edicion_pagada']);
            $table->decimal('monto', 10, 2);
            $table->string('metodo_pago', 30)->default('EFECTIVO');
            $table->timestamps();

            $table->index(['caja_turno_id']);
            $table->index(['evento_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caja_movimientos');
    }
};

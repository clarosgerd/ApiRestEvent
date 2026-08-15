<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Caja de cobro presencial (14/08/2026) — ver
 * PLAN-CAJA-COBRO-PRESENCIAL-14082026.md. Un cajero no puede cobrar sin
 * turno abierto (regla dura en CajaController) — esto es lo que hace
 * confiable el control de cierre pedido por el stakeholder (alto
 * tráfico, ~300 participantes/día).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('caja_turnos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evento_id')->constrained('eventos')->cascadeOnDelete();
            $table->foreignId('admin_user_id')->constrained('admin_users')->cascadeOnDelete();
            $table->decimal('fondo_inicial', 10, 2)->default(0);
            // Calculados al cerrar — null mientras el turno sigue abierto.
            $table->decimal('monto_esperado', 10, 2)->nullable();
            $table->decimal('monto_contado', 10, 2)->nullable();
            $table->decimal('diferencia', 10, 2)->nullable();
            $table->enum('estado', ['abierto', 'cerrado'])->default('abierto');
            $table->text('notas')->nullable();
            $table->timestamp('abierto_at');
            $table->timestamp('cerrado_at')->nullable();
            $table->timestamps();

            // "¿tengo un turno abierto?" corre en cada cobro — con varios
            // cajeros en paralelo y alto tráfico, tiene que ser barata.
            $table->index(['evento_id', 'admin_user_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caja_turnos');
    }
};

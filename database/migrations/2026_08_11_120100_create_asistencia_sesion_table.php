<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agenda y sesiones de congreso — ver
 * 2026_08_11_120000_create_sesiones_congreso_table.php.
 *
 * `staff_admin_user_id` es explícito acá (a diferencia de
 * `participantes.checked_in_at`, que no guarda quién lo hizo en la
 * misma fila, solo en el audit log genérico) — el PRD pide trazabilidad
 * de qué staff validó el ingreso en cada sesión/sala.
 *
 * Unique (`sesion_congreso_id`,`participante_id`): mismo criterio de
 * idempotencia que el resto del sistema — re-escanear a alguien ya
 * acreditado en esa sesión no duplica la fila.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asistencia_sesion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sesion_congreso_id')->constrained('sesiones_congreso')->cascadeOnDelete();
            $table->foreignId('participante_id')->constrained('participantes')->cascadeOnDelete();
            $table->timestamp('checkin_at')->useCurrent();
            $table->foreignId('staff_admin_user_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['sesion_congreso_id', 'participante_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asistencia_sesion');
    }
};

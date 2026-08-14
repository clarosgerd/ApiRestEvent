<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ver brain/PLAN-ASIGNACION-STAFF-SESIONES-CONGRESO-13082026.md. Vincula
 * un participante inscrito bajo un form_type con `es_staff=true` a una o
 * más sesiones de congreso que apoya — muchos a muchos (un ayudante puede
 * cubrir varias sesiones, una sesión puede tener varios ayudantes).
 *
 * Distinta de `asistencia_sesion`: esa registra que alguien ASISTIÓ a una
 * sesión (check-in de un asistente); esta registra que alguien fue
 * ASIGNADO para apoyar/dar soporte en una sesión (staff, decisión del
 * organizador, no requiere check-in).
 *
 * `asignado_por_admin_user_id` es auditoría de quién hizo la asignación,
 * mismo criterio que `staff_admin_user_id` en asistencia_sesion.
 *
 * Unique (`sesion_congreso_id`,`participante_id`): no tiene sentido
 * asignar al mismo ayudante dos veces a la misma sesión.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sesion_congreso_staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sesion_congreso_id')->constrained('sesiones_congreso')->cascadeOnDelete();
            $table->foreignId('participante_id')->constrained('participantes')->cascadeOnDelete();
            $table->foreignId('asignado_por_admin_user_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['sesion_congreso_id', 'participante_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sesion_congreso_staff');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ver brain/PLAN-VINCULACION-PONENTES-SESIONES-CONGRESO-13082026.md. La
 * tabla `sesion_congreso_staff` (13/08/2026, misma sesión) pasa a cubrir
 * dos roles distintos, no solo staff — un ponente inscrito puede
 * vincularse a la(s) sesión(es) donde expone con el mismo mecanismo que
 * ya existía para asignar ayudantes. El nombre de la tabla se mantiene
 * (evita una migración de rename + actualizar todas las referencias) pero
 * conceptualmente ahora es "participantes vinculados a una sesión, por
 * rol".
 *
 * Se agranda el unique existente para incluir `rol`: en teoría el mismo
 * participante podría figurar como staff Y como ponente de una misma
 * sesión (caso raro pero no absurdo — alguien que expone y además ayuda),
 * no hay motivo para bloquearlo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sesion_congreso_staff', function (Blueprint $table) {
            $table->enum('rol', ['staff', 'ponente'])->default('staff')->after('participante_id');
        });

        // El índice único viejo (sesion_congreso_id, participante_id) es el
        // que sostiene la FK de sesion_congreso_id — MySQL/InnoDB no deja
        // dropearlo sin tener antes otro índice que empiece con esa misma
        // columna para reemplazarlo. Por eso el nuevo único se crea PRIMERO
        // (también arranca con sesion_congreso_id) y recién después se
        // puede dropear el viejo.
        Schema::table('sesion_congreso_staff', function (Blueprint $table) {
            $table->unique(['sesion_congreso_id', 'participante_id', 'rol'], 'sesion_congreso_staff_sesion_participante_rol_unique');
        });

        Schema::table('sesion_congreso_staff', function (Blueprint $table) {
            $table->dropUnique(['sesion_congreso_id', 'participante_id']);
        });
    }

    public function down(): void
    {
        Schema::table('sesion_congreso_staff', function (Blueprint $table) {
            $table->unique(['sesion_congreso_id', 'participante_id']);
        });

        Schema::table('sesion_congreso_staff', function (Blueprint $table) {
            $table->dropUnique('sesion_congreso_staff_sesion_participante_rol_unique');
            $table->dropColumn('rol');
        });
    }
};

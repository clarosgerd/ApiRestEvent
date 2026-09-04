<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deshabilitar una categoría sin ocultarla (04/09/2026) — mismo patrón que
 * `talleres.permite_inscripcion` (28/08/2026, ver
 * PLAN-TALLER-PERMITE-INSCRIPCION-28082026.md): la categoría sigue visible
 * para el participante en elascenso/event, pero no se puede elegir.
 * `Category` no tenía ningún concepto de activo/inactivo hasta ahora — a
 * diferencia de `talleres.activo` (que oculta del todo), acá no existe un
 * campo "activo" que reemplazar, se agrega directamente el equivalente a
 * "visible pero no seleccionable". Aditiva pura: default `true`, ninguna
 * categoría existente cambia de comportamiento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->boolean('permite_inscripcion')->default(true)->after('color');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('permite_inscripcion');
        });
    }
};

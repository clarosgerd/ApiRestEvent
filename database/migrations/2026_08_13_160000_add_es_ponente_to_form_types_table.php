<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ver brain/PLAN-VINCULACION-PONENTES-SESIONES-CONGRESO-13082026.md. Mismo
 * criterio que `es_staff` (13/08/2026, mismo día) — flag explícito en vez
 * de inferir del nombre/tipo del form_type. Un participante inscrito bajo
 * un form_type con este flag es asignable como ponente de una o más
 * sesiones (reusa la tabla `sesion_congreso_staff`, con la columna `rol`
 * agregada en la migración siguiente).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_types', function (Blueprint $table) {
            $table->boolean('es_ponente')->default(false)->after('es_staff');
        });
    }

    public function down(): void
    {
        Schema::table('form_types', function (Blueprint $table) {
            $table->dropColumn('es_ponente');
        });
    }
};

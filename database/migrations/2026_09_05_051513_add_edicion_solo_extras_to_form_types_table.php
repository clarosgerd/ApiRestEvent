<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Edición restringida a solo souvenirs/talleres (04/09/2026) — pedido de
 * organizadores de congresos: no quieren que el participante pueda editar
 * sus propios datos personales (ni la categoría) al modificar su
 * inscripción, solo agregar souvenirs/talleres. Aditiva pura: default
 * `false`, ningún form_type existente cambia de comportamiento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_types', function (Blueprint $table) {
            $table->boolean('edicion_solo_extras')->default(false)->after('campos_ocultos');
        });
    }

    public function down(): void
    {
        Schema::table('form_types', function (Blueprint $table) {
            $table->dropColumn('edicion_solo_extras');
        });
    }
};

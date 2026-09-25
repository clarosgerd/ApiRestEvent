<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Solo un participante por inscripción" (26/09/2026) — para tipos de
 * formulario como empresa expositora, staff o ponente, donde una inscripción
 * es una sola persona/empresa. `permite_inscripcion_grupal` NO sirve para esto:
 * solo fija un tope cuando está activo, y apagado no limita nada. Aditiva pura:
 * default `false`, ningún form_type existente cambia de comportamiento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_types', function (Blueprint $table) {
            $table->boolean('un_solo_participante')->default(false)->after('edicion_solo_extras');
        });
    }

    public function down(): void
    {
        Schema::table('form_types', function (Blueprint $table) {
            $table->dropColumn('un_solo_participante');
        });
    }
};

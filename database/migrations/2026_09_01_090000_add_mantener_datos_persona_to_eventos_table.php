<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Purgar datos de Persona/Participante en inscripciones canceladas
 * (01/09/2026) — ver PLAN-PURGAR-DATOS-PERSONA-CANCELADA-01092026.md.
 * Bandera configurable por evento: si está en `true` (default), no cambia
 * nada del comportamiento actual. Si el organizador la apaga, una
 * inscripción que termina `cancelled` dispara el borrado de su
 * `Participante` (y de la cuenta `Persona`, si no tiene otra inscripción
 * vigente en ningún otro evento) — ver
 * App\Actions\PurgarDatosPersonaCanceladaAction.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->boolean('mantener_datos_persona')->default(true)->after('acepta_usd');
        });
    }

    public function down(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->dropColumn('mantener_datos_persona');
        });
    }
};

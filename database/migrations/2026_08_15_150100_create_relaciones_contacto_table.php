<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de relación del contacto de emergencia (15/08/2026) — ver
 * elascenso/event/brain/PLAN-CATALOGOS-GLOBALES-15082026.md. Mismo shape
 * que `sexos`/`tipos_evento`. Pedido por el usuario tras notar que
 * `contacto_emergencia_participantes.relacion` es hoy texto libre, con
 * datos reales ya inconsistentes (mezcla de códigos en inglés y texto en
 * español: FAM/WIF/Familiar/FRI/Pareja/SPO/Hermano/HUS). Este catálogo es
 * aditivo — NO se relaciona como FK ni se toca la columna `relacion`
 * existente en esta sesión, queda listo para una futura migración del
 * formulario a un <select> real.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('relaciones_contacto', function (Blueprint $table) {
            $table->tinyIncrements('id');
            $table->string('nombre', 80);
            $table->boolean('activo')->default(true);
        });

        DB::table('relaciones_contacto')->insert([
            ['nombre' => 'Madre', 'activo' => true],
            ['nombre' => 'Padre', 'activo' => true],
            ['nombre' => 'Hijo/a', 'activo' => true],
            ['nombre' => 'Hermano/a', 'activo' => true],
            ['nombre' => 'Esposo/a', 'activo' => true],
            ['nombre' => 'Pareja', 'activo' => true],
            ['nombre' => 'Familiar', 'activo' => true],
            ['nombre' => 'Amigo/a', 'activo' => true],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('relaciones_contacto');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de sexo (15/08/2026) — ver
 * elascenso/event/brain/PLAN-CATALOGOS-GLOBALES-15082026.md. Mismo shape
 * que `tipos_evento` (sin timestamps, `activo` boolean). Respalda
 * `categories.sexo_id`, que hoy es una columna suelta sin FK y 100% NULL
 * en los datos reales — este catálogo es aditivo, no se relaciona como FK
 * en esta sesión (decisión explícita del usuario), y no toca
 * `participantes.genero` (campo distinto, no relacionado).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sexos', function (Blueprint $table) {
            $table->tinyIncrements('id');
            $table->string('nombre', 80);
            $table->boolean('activo')->default(true);
        });

        DB::table('sexos')->insert([
            ['nombre' => 'Masculino', 'activo' => true],
            ['nombre' => 'Femenino', 'activo' => true],
            ['nombre' => 'Mixto', 'activo' => true],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('sexos');
    }
};

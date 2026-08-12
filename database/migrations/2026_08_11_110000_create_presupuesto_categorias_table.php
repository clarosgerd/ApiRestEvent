<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Presupuesto de un evento — control financiero del organizador. Ver
 * ApiRestEvent/brain/api_rest_event/PRD-presupuesto_de_un_evento.md y
 * elascenso/event/brain/ (sesión 11/08/2026). Distinto de la Liquidación
 * de utilidades entre socios (PRD-Consolidacion-only-superadmin.md) — no
 * hay relación de cálculo entre ambas.
 *
 * Catálogo fijo de rubros (Marketing, Logística, Premios, Patrocinio,
 * Donación...) — cada categoría es de un tipo fijo (una categoría de
 * gasto no puede reusarse para un ingreso). Editable solo por
 * super_admin, igual que `socios`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presupuesto_categorias', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->enum('tipo', ['ingreso', 'gasto']);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // Seed inicial con los ejemplos del PRD — editable después desde
        // el panel (Presupuesto > Categorías), esto solo evita arrancar
        // en blanco.
        DB::table('presupuesto_categorias')->insert([
            ['nombre' => 'Marketing', 'tipo' => 'gasto', 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Logística', 'tipo' => 'gasto', 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Premios', 'tipo' => 'gasto', 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Patrocinio', 'tipo' => 'ingreso', 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Donación', 'tipo' => 'ingreso', 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('presupuesto_categorias');
    }
};

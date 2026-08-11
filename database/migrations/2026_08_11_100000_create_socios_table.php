<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Consolidación financiera (liquidación de utilidades) — solo superadmin.
 * Ver ApiRestEvent/brain/api_rest_event/PRD-Consolidacion-only-superadmin.md
 * y elascenso/event/brain/ (sesión 11/08/2026).
 *
 * Tabla configurable de socios (en vez de hardcodear nombres/porcentajes en
 * código) — editable desde el panel admin sin deploy. `porcentaje` usa la
 * misma convención que `Organizador.comision_especial`: puntos 0-100, no
 * fracción 0-1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('socios', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->decimal('porcentaje', 5, 2);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // Seed inicial con los 4 socios reales del PRD — el superadmin los
        // puede editar después desde el panel; esto solo evita arrancar en
        // blanco (sin socios activos no se puede liquidar ningún evento).
        DB::table('socios')->insert([
            ['nombre' => 'Mario', 'porcentaje' => 40.00, 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Carlitos', 'porcentaje' => 35.00, 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Galia', 'porcentaje' => 15.00, 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Norman', 'porcentaje' => 10.00, 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('socios');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivote participante ↔ sesión de congreso cuando la sesión pertenece a
 * un taller y fue seleccionada durante la inscripción. `unit_price`,
 * `discount` y `total` son snapshot financiero — preservan el importe
 * aunque luego cambien los precios del taller o de la sesión. Ver
 * brain/PLAN-CONGRESOS-TALLERES-HORARIOS-IMPLEMENTACION.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('participante_taller_sesion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('participante_id')->constrained('participantes')->cascadeOnDelete();
            $table->foreignId('sesion_congreso_id')->constrained('sesiones_congreso')->cascadeOnDelete();
            $table->foreignId('taller_id')->constrained('talleres')->cascadeOnDelete();
            $table->decimal('unit_price', 10, 2);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->timestamps();

            $table->unique(['participante_id', 'sesion_congreso_id'], 'participante_taller_sesion_unique');
            $table->index('participante_id', 'participante_taller_sesion_participante_idx');
            $table->index('sesion_congreso_id', 'participante_taller_sesion_sesion_idx');
            $table->index('taller_id', 'participante_taller_sesion_taller_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participante_taller_sesion');
    }
};
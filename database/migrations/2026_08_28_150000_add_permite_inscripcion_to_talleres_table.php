<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deshabilitar un taller sin ocultarlo (28/08/2026) — ver
 * brain/PLAN-TALLER-PERMITE-INSCRIPCION-28082026.md. Pedido del usuario:
 * `activo=false` ya existía pero oculta el taller por completo del
 * participante (EventoController::show() filtra por `activo`); acá se
 * agrega un estado distinto — visible, pero no seleccionable. Aditiva
 * pura: default `true`, ningún taller existente cambia de comportamiento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('talleres', function (Blueprint $table) {
            $table->boolean('permite_inscripcion')->default(true)->after('activo');
        });
    }

    public function down(): void
    {
        Schema::table('talleres', function (Blueprint $table) {
            $table->dropColumn('permite_inscripcion');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aviso de numeración vs. género/edad real en entrega de kit (16/09/2026).
 *
 * Un rango de numeración (bib) por color, ligado a una categoría —
 * DELIBERADAMENTE independiente de `categories.sexo_id`/`edad_min`/
 * `edad_max` (esas quedan sin usar, la categoría que elige el participante
 * es una sola, ej. "5K", no se fragmenta por género/edad). Este catálogo
 * responde: dentro de la categoría X, ¿qué color/rango de números le
 * corresponde a alguien de género Y y edad entre A y B? Se compara contra
 * el `numero_corredor` real que tiene asignado — ver
 * App\Support\NumeracionRangoChecker.
 *
 * Una categoría sin ninguna fila acá tiene comportamiento actual, sin
 * cambios — mismo criterio de compatibilidad que `category_price_periods`/
 * `item_stock` (sin filas = sin control, no rompe eventos existentes).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('numeracion_rangos', function (Blueprint $table) {
            $table->id();

            // categories.id es increments() (INT UNSIGNED, no BIGINT) —
            // foreignId() crearía un BIGINT y rompería la FK (errno 150),
            // mismo gotcha ya resuelto en category_price_periods.
            $table->unsignedInteger('category_id');
            $table->foreign('category_id')->references('id')->on('categories')->cascadeOnDelete();

            // generos.id es tinyIncrements() (TINYINT UNSIGNED).
            $table->unsignedTinyInteger('genero_id');
            $table->foreign('genero_id')->references('id')->on('generos');

            $table->unsignedInteger('edad_min');
            $table->unsignedInteger('edad_max');
            $table->string('color', 7);
            $table->unsignedInteger('numero_min');
            $table->unsignedInteger('numero_max');
            $table->timestamps();

            $table->index(['category_id', 'genero_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('numeracion_rangos');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Precio por categoría de un combo de talleres (13/10/2026, pedido del
 * usuario) — el MISMO combo (mismos talleres) puede costar distinto según
 * la categoría del participante. Ej.: "Taller A + Taller B" cuesta Bs 1000
 * para "Especialistas" y Bs 800 para "Residentes".
 *
 * `category_id` nullable: NULL es el precio DEFAULT, el que se usa para
 * cualquier categoría que no tenga su propia fila — mismo rol que "sin
 * período" en `category_price_periods`/`taller_price_periods`, pero acá si
 * un combo no tiene NINGUNA fila (ni siquiera la default) simplemente no se
 * ofrece a nadie — no hay fallback a un "precio base" porque un combo
 * nunca tuvo uno (ver App\Models\TallerCombo::precioParaCategoria()).
 *
 * Sin UNIQUE a nivel BD: MySQL trata cada NULL como distinto en una UNIQUE
 * KEY, así que no evitaría dos filas default para el mismo combo. La
 * validación de "a lo sumo una fila por categoría (o default) por combo"
 * vive en TallerComboPrecioController.
 *
 * `price_usd` (13/10/2026, mismo día — el usuario pidió agregarlo antes de
 * que esto llegara a subirse a ningún ambiente real) — precio en USD fijo
 * para esta categoría/combo, nullable: igual que con los talleres
 * individuales, un combo puede no tener precio USD cargado (el evento no
 * usa "Precio USD fijo", o todavía no se configuró). Ver
 * App\Models\TallerCombo::precioUsdParaCategoria().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taller_combo_precios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('taller_combo_id')->constrained('taller_combos')->cascadeOnDelete();
            // categories.id es unsignedInteger (increments), no bigint —
            // foreignId() rompería la FK (errno 150), mismo gotcha que
            // category_price_periods.
            $table->unsignedInteger('category_id')->nullable();
            $table->foreign('category_id')->references('id')->on('categories')->cascadeOnDelete();
            $table->decimal('precio', 10, 2);
            $table->decimal('price_usd', 10, 2)->nullable();
            $table->timestamps();

            $table->index(['taller_combo_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taller_combo_precios');
    }
};

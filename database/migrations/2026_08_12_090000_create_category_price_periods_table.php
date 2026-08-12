<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Precios por período (12/08/2026) — ver PRD-precios-periodos-fechas.md.
 *
 * Los períodos viven en `categories`, no en `form_types.precio_base` —
 * decisión ya tomada con el usuario (ver sección "Decisiones ya
 * tomadas" del PRD, punto 1): `categories.price` es donde vive el
 * precio que realmente se cobra hoy cuando `requiere_categoria=true`.
 * Cuando `requiere_categoria=false` el precio sale de
 * `form_types.precio_base` directo, sin períodos (sección 0 del PRD).
 *
 * `fecha_hasta >= fecha_desde` y "sin traslapes con otro período de la
 * misma categoría" se validan en el Request (StorePricePeriodRequest),
 * no acá — un rango de fechas no se puede expresar como UNIQUE/CHECK
 * portable.
 *
 * Una categoría sin ninguna fila acá tiene comportamiento actual, sin
 * cambios: se cobra `categories.price` tal cual — mismo criterio de
 * compatibilidad que `item_stock` en kit/tallas/stock (sin filas = sin
 * control, no rompe eventos existentes).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_price_periods', function (Blueprint $table) {
            $table->id();
            // `categories.id` es `increments()` (INT unsigned, no BIGINT
            // como el resto de las tablas nuevas) — `foreignId()` crea un
            // BIGINT y rompe la FK (errno 150). Mismo tipo a mano, como
            // hace `categories.event_id` con `eventos.id`.
            $table->unsignedInteger('category_id');
            $table->foreign('category_id')->references('id')->on('categories')->cascadeOnDelete();
            $table->string('nombre');
            $table->decimal('price', 10, 2);
            $table->date('fecha_desde');
            $table->date('fecha_hasta');
            $table->timestamps();

            $table->index(['category_id', 'fecha_desde', 'fecha_hasta']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_price_periods');
    }
};

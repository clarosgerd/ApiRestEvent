<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Precios por período para talleres (12/10/2026) — mismo patrón que
 * `category_price_periods` (ver PRD-precios-periodos-fechas.md), pero
 * aplicado a `talleres` en vez de `categories`. Un taller sin ninguna fila
 * acá tiene comportamiento actual sin cambios: se cobra `talleres.precio`
 * tal cual (o el override de `sesiones_congreso.precio` si existe, que
 * sigue ganando siempre — ver ResolverPrecioTallerData).
 *
 * `fecha_hasta >= fecha_desde` y "sin traslapes con otro período del mismo
 * taller" se validan en el Request, no acá — un rango de fechas no se
 * puede expresar como UNIQUE/CHECK portable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taller_price_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('taller_id')->constrained('talleres')->cascadeOnDelete();
            $table->string('nombre');
            $table->decimal('price', 10, 2);
            $table->decimal('price_usd', 10, 2)->nullable();
            $table->date('fecha_desde');
            $table->date('fecha_hasta');
            $table->timestamps();

            $table->index(['taller_id', 'fecha_desde', 'fecha_hasta']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taller_price_periods');
    }
};

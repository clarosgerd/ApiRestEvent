<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Precio USD fijo, sin tipo de cambio (19/08/2026) — ver
 * brain/PLAN-PRECIO-USD-FIJO-19082026.md. Pedido: cobrar en dólares a
 * extranjeros con un precio cargado directo por el organizador, sin
 * derivarlo del precio BOB vía tasa de cambio (a diferencia de
 * `acepta_usd`, que sí usa tipo_cambio.php).
 *
 * ADITIVO, no toca nada existente: default `false`/`NULL` — ningún evento
 * ni categoría cambia de comportamiento hasta que alguien prenda el
 * checkbox nuevo en admin-eventos. El camino `acepta_usd` con tipo de
 * cambio sigue intacto para todos los eventos que ya lo usan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->boolean('usd_precio_fijo')->default(false)->after('acepta_usd');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->decimal('price_usd', 10, 2)->nullable()->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->dropColumn('usd_precio_fijo');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('price_usd');
        });
    }
};

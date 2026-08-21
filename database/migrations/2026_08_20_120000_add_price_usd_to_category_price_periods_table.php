<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Precio USD fijo por período (20/08/2026) — cierra un gap real entre dos
 * features que se construyeron separadas: precios por período (12/08,
 * `category_price_periods.price` en BOB) y precio USD fijo (19/08,
 * `categories.price_usd`, un valor plano sin relación con los períodos).
 * Antes de esto, un evento con ambos prendidos a la vez cobraba el mismo
 * monto en USD sin importar qué período estuviera vigente, mientras el
 * monto en BOB sí variaba — ver PrecioVigenteData::paraCategoria().
 *
 * Nullable a propósito: un período sin `price_usd` cargado cae a
 * `categories.price_usd` (mismo criterio de fallback que ya usa
 * `precio` para categorías sin períodos), así que cargar esta columna es
 * opcional y no rompe periodos ya creados.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('category_price_periods', function (Blueprint $table) {
            $table->decimal('price_usd', 10, 2)->nullable()->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('category_price_periods', function (Blueprint $table) {
            $table->dropColumn('price_usd');
        });
    }
};

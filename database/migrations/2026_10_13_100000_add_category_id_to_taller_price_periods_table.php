<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Período de precio por categoría (13/10/2026, pedido del usuario) — un
 * período de precio de taller puede opcionalmente asociarse a una
 * categoría; sin categoría, sigue siendo general (aplica a cualquier
 * categoría sin período propio) — mismo criterio que
 * taller_combo_precios.category_id, ver PrecioVigenteTallerData.
 *
 * Aditiva sobre taller_price_periods, ya en UAT — no se edita la
 * migración original, se agrega con ALTER (mismo criterio ya usado para
 * price_usd en taller_combo_precios).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('taller_price_periods', function (Blueprint $table) {
            // categories.id es unsignedInteger (increments), no bigint —
            // foreignId() rompería la FK (errno 150), mismo gotcha ya
            // documentado en taller_combo_precios.
            $table->unsignedInteger('category_id')->nullable()->after('taller_id');
            $table->foreign('category_id')->references('id')->on('categories')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('taller_price_periods', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn('category_id');
        });
    }
};

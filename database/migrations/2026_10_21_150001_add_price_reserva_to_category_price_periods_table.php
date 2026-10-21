<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reserva de cupo con seña (21/10/2026) — mismo patrón que
 * add_price_usd_to_category_price_periods_table: un período puede fijar su
 * propio precio de reserva; si no lo tiene, cae a `categories.price_reserva`
 * (ver PrecioVigenteData::precioReservaDelPeriodo()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('category_price_periods', function (Blueprint $table) {
            $table->decimal('price_reserva', 10, 2)->nullable()->after('price_usd');
        });
    }

    public function down(): void
    {
        Schema::table('category_price_periods', function (Blueprint $table) {
            $table->dropColumn('price_reserva');
        });
    }
};

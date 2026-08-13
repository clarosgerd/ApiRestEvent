<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mapa de ubicación de delivery (12/08/2026) — hasta ahora el único dato
 * que tenía la empresa de delivery era `direccion` (texto libre). Estas
 * columnas guardan el pin que el participante puede arrastrar en el mapa
 * al tildar "quiero delivery" — opcional, no reemplaza `direccion`, la
 * complementa. Mismo tipo/precisión que `coordinates.lat/lng` (ver
 * 2026_07_01_130436_create_coordinates_table.php) para consistencia.
 * Nulas mientras el participante no toque el mapa (o no pida delivery).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('participantes', function (Blueprint $table) {
            $table->float('delivery_lat', 10, 6)->nullable()->after('estado_delivery');
            $table->float('delivery_lng', 10, 6)->nullable()->after('delivery_lat');
        });
    }

    public function down(): void
    {
        Schema::table('participantes', function (Blueprint $table) {
            $table->dropColumn(['delivery_lat', 'delivery_lng']);
        });
    }
};

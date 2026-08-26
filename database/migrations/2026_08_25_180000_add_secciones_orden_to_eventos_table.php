<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Orden configurable de secciones en la pantalla de tipos de formulario
 * (25/08/2026) — pedido: que el organizador pueda elegir, desde
 * admin-eventos, en qué orden se muestran los bloques de información del
 * evento (descripción, calendario, cuenta regresiva, video/imagen,
 * auspiciadores, galería del kit, mapa de ruta, agenda) y las tarjetas de
 * tipo de formulario en `elascenso/event` (#screen-form-types).
 *
 * ADITIVO, no toca nada existente: `NULL` por default — el frontend cae al
 * orden actual (hardcodeado) para cualquier evento que no lo configuró.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->json('secciones_orden')->nullable()->after('usd_precio_fijo');
        });
    }

    public function down(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->dropColumn('secciones_orden');
        });
    }
};

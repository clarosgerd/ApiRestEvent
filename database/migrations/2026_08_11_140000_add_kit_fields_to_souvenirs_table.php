<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kit, tallas, stock y lista de espera — ver
 * ApiRestEvent/brain/api_rest_event/PRD-kit-tallas-stock-lista-espera.md
 * (sesión 11/08/2026).
 *
 * Unifica "kit incluido" y "souvenirs opcionales a la venta" bajo el
 * mismo modelo `Souvenir` (sin renombrar la tabla — ver la decisión de
 * terminología del PRD: de acá para adelante se le dice "ítem" en
 * pantallas/correos, pero el modelo/tabla sigue siendo Souvenir porque
 * ya está en rutas y claves JSON que consume el frontend de producción).
 * Migración aditiva — no rompe la compra de souvenirs opcionales
 * existente, que sigue funcionando igual si no se le configura
 * talla/sexo/stock.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('souvenirs', function (Blueprint $table) {
            $table->boolean('incluido')->default(false)->after('price');
            $table->string('foto_url')->nullable()->after('icon');
            $table->boolean('requiere_talla')->default(false)->after('incluido');
            $table->boolean('requiere_sexo')->default(false)->after('requiere_talla');
        });
    }

    public function down(): void
    {
        Schema::table('souvenirs', function (Blueprint $table) {
            $table->dropColumn(['incluido', 'foto_url', 'requiere_talla', 'requiere_sexo']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Precio USD fijo de talleres (19/08/2026) — extiende el modo "Precio USD
 * fijo" (ver PLAN-PRECIO-USD-FIJO-19082026.md) para cubrir talleres,
 * antes explícitamente fuera de alcance (cualquier taller seleccionado
 * bloqueaba el pago en USD). Mismo patrón override que ya existe para el
 * precio en Bs: `sesiones_congreso.price_usd` gana si está cargado, si no
 * hereda de `talleres.price_usd`. Ambas columnas nullable — un evento sin
 * "Precio USD fijo" nunca las necesita.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('talleres', function (Blueprint $table) {
            $table->decimal('price_usd', 10, 2)->nullable()->after('precio');
        });

        Schema::table('sesiones_congreso', function (Blueprint $table) {
            $table->decimal('price_usd', 10, 2)->nullable()->after('precio');
        });
    }

    public function down(): void
    {
        Schema::table('talleres', function (Blueprint $table) {
            $table->dropColumn('price_usd');
        });

        Schema::table('sesiones_congreso', function (Blueprint $table) {
            $table->dropColumn('price_usd');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recategorización visual por edad/género (23/09/2026) — a pedido del
 * usuario, `numero_min`/`numero_max` dejan de ser obligatorios al cargar
 * un rango: alcanza con género+edad para recategorizar (ver
 * App\Support\RecategorizacionResolver), el rango de numeración/color solo
 * hace falta para el aviso de bib (App\Support\NumeracionRangoChecker, ya
 * ajustado para no alertar cuando estos campos vienen null).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('numeracion_rangos', function (Blueprint $table) {
            $table->unsignedInteger('numero_min')->nullable()->change();
            $table->unsignedInteger('numero_max')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('numeracion_rangos', function (Blueprint $table) {
            $table->unsignedInteger('numero_min')->nullable(false)->change();
            $table->unsignedInteger('numero_max')->nullable(false)->change();
        });
    }
};

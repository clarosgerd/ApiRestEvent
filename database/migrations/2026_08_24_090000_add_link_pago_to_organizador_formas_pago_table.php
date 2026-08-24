<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pago pendiente USD (24/08/2026) — link de pago configurable por
     * organizador para el método manual "pendiente_usd" (ver plan "Pago
     * pendiente USD (link por correo, expira 24h)"). Columna genérica en
     * el pivote (aplica a cualquier forma de pago), pero en la práctica
     * solo se llena para la fila cuyo forma_pago_id es "pendiente_usd" —
     * ver Organizador::linkPagoPendienteUsd().
     */
    public function up(): void
    {
        Schema::table('organizador_formas_pago', function (Blueprint $table) {
            $table->string('link_pago')->nullable()->after('activo');
        });
    }

    public function down(): void
    {
        Schema::table('organizador_formas_pago', function (Blueprint $table) {
            $table->dropColumn('link_pago');
        });
    }
};

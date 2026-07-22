<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pivote: qué métodos de pago eligió cada organizador (los del sistema,
     * el suyo propio, o una combinación de ambos). Fuente de verdad única
     * para la elección del organizador — el panel de administración que
     * construirá otro equipo escribe/edita filas acá.
     */
    public function up(): void
    {
        Schema::create('organizador_formas_pago', function (Blueprint $table) {
            $table->id();
            // organizadores.id es INT UNSIGNED (Schema::increments), no
            // BIGINT UNSIGNED — no se puede usar foreignId() acá.
            $table->unsignedInteger('organizador_id');
            $table->foreign('organizador_id')->references('id')->on('organizadores')->cascadeOnDelete();
            $table->foreignId('forma_pago_id')->constrained('formas_pagos')->cascadeOnDelete();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['organizador_id', 'forma_pago_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizador_formas_pago');
    }
};

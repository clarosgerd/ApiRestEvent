<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Convierte formas_pagos de catálogo vacío/desconectado a la fuente de
     * verdad de qué métodos de pago existen: los del sistema (organizador_id
     * NULL: sip, multipago, pendiente) y los propios de cada organizador
     * (organizador_id != NULL — su propio convenio/gateway o instrucciones
     * manuales).
     */
    public function up(): void
    {
        Schema::table('formas_pagos', function (Blueprint $table) {
            // Identificador estable usado en código (registro.php, frontend)
            // — independiente del nombre visible, que puede cambiar.
            $table->string('slug')->after('id');
            // organizadores.id es INT UNSIGNED (Schema::increments), no
            // BIGINT UNSIGNED — no se puede usar foreignId() acá, el tipo
            // debe calzar exacto para la FK.
            $table->unsignedInteger('organizador_id')->nullable()->after('slug');
            $table->foreign('organizador_id')->references('id')->on('organizadores')->nullOnDelete();
            // 'integrado' = tiene código real detrás (identificado por
            // "pasarela": sip, multipago, ...). 'manual' = solo se muestran
            // instrucciones (config) y el pago se confirma fuera del sistema.
            $table->enum('tipo', ['integrado', 'manual'])->default('manual')->after('pasarela');
            // Datos operativos del método: instrucciones/cuenta bancaria para
            // los manuales. Nunca credenciales de gateways integrados — esas
            // viven solo en el .env de cada integración (sip-payment-integration,
            // multipago-payment-integration), no en esta tabla.
            $table->json('config')->nullable()->after('tipo');

            $table->unique('slug');
        });
    }

    public function down(): void
    {
        Schema::table('formas_pagos', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropForeign(['organizador_id']);
            $table->dropColumn(['slug', 'organizador_id', 'tipo', 'config']);
        });
    }
};

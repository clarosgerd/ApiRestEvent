<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inscripción en BOB y USD (18/08/2026) — ver
 * brain/PLAN-INSCRIPCION-BOB-USD-IMPLEMENTACION.md. Snapshot de la moneda
 * de cobro en `registrations`: solo se persiste cuando el usuario eligió
 * USD; en BOB los campos quedan null y el comportamiento es idéntico al
 * actual (default null).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->enum('moneda_pago', ['BOB', 'USD'])->default('BOB')->after('pay_order_number');
            $table->decimal('tipo_cambio_aplicado', 10, 4)->nullable()->after('moneda_pago');
            $table->decimal('total_pagado', 10, 2)->nullable()->after('tipo_cambio_aplicado');
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropColumn(['moneda_pago', 'tipo_cambio_aplicado', 'total_pagado']);
        });
    }
};
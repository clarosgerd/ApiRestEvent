<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reserva de cupo con seña (21/10/2026) — rastro acotado del cobro online
 * de la reserva (decisión confirmada con el usuario: columnas chicas acá,
 * no unificar el ledger con CajaMovimiento, que siempre pertenece a un
 * turno de Caja presencial). Se escriben UNA sola vez, dentro de
 * RegistrationService::updatePaymentStatus() cuando el status pasa a
 * 'paid' y saldo_pendiente > 0.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->decimal('monto_reserva_cobrado', 10, 2)->nullable()->after('saldo_pendiente');
            $table->timestamp('reserva_pagada_at')->nullable()->after('monto_reserva_cobrado');
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropColumn(['monto_reserva_cobrado', 'reserva_pagada_at']);
        });
    }
};

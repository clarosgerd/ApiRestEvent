<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pago online del saldo pendiente — autoservicio (22/10/2026, pedido real
 * del usuario). Mismo shape que `pagos_adicionales_inscripcion`
 * (ver create_pagos_adicionales_inscripcion_table), pero sin
 * participantes_payload/totales_payload: acá no se edita nada al
 * confirmarse el pago, solo se deja registrations.saldo_pendiente en 0
 * (ver ConfirmarPagoSaldoAction).
 *
 * `saldo`/`fee` se guardan por separado de `monto` para poder mostrar el
 * desglose en el correo/resumen ("Saldo + Cargo de servicio = Monto") —
 * el fee solo se cobra en ESTE canal (online, cubre el costo real de la
 * pasarela), nunca cuando el saldo se cobra en Caja (efectivo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos_saldo_inscripcion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained('registrations')->cascadeOnDelete();
            // Alias propio (ej. 'SA-XXXXXXXX') — distinto de 'AD-' (pago
            // adicional) y de 'LA-' (referencia de inscripción real), nunca
            // colisiona con ninguno de los dos.
            $table->string('referencia', 30)->unique();
            $table->decimal('saldo', 10, 2);
            $table->decimal('fee', 10, 2);
            $table->decimal('monto', 10, 2);
            $table->string('moneda_pago', 3)->default('BOB');
            $table->string('qr_id')->nullable();
            $table->string('pay_order_number')->nullable();
            $table->enum('pago_status', ['pending', 'paid', 'expired', 'error'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('notificado_at')->nullable();
            $table->timestamps();

            $table->index('registration_id');
            $table->index('pago_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos_saldo_inscripcion');
    }
};

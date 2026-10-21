<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reserva de cupo con seña (21/10/2026, pedido real del usuario) — cuánto
 * falta cobrar de una inscripción `pago_status='paid'` por haber pagado
 * solo la reserva (seña) al inscribirse. 0 para cualquier inscripción
 * pagada normalmente, sin cambios de comportamiento. Se calcula y persiste
 * UNA sola vez en el alta (o al reeditar mientras sigue pendiente) — nunca
 * se recalcula contra el catálogo vigente el día que Caja lo cobra, mismo
 * criterio que SnapshotInscripcionPagadaData. Caja lo cobra con
 * CajaController::cobrarSaldo(), que lo deja en 0 sin tocar pago_status
 * (ya es 'paid' desde el alta).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->decimal('saldo_pendiente', 10, 2)->default(0)->after('pago_status');
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropColumn('saldo_pendiente');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reserva de cupo con seña (22/10/2026, pedido real del usuario) — fecha
 * límite configurable para pagar el saldo pendiente de una reserva. Nullable
 * a propósito: sin cargarla, `index.php` sigue mostrando la fecha de inicio
 * del evento como plazo (comportamiento ya implementado el 21/10/2026, sin
 * cambios para eventos que no la configuren).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->date('fecha_limite_pago_reserva')->nullable()->after('forzar_qr_pago_adicional');
        });
    }

    public function down(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->dropColumn('fecha_limite_pago_reserva');
        });
    }
};

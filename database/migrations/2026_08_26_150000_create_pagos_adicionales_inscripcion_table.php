<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cobro real por SIP del monto adicional al agregar un taller a una
 * inscripción pagada (26/08/2026, pedido del usuario). Ver
 * PLAN-COBRO-SIP-ADICIONAL-26082026.md.
 *
 * Tabla nueva y separada (no un campo padre/hijo en `registrations`) —
 * decisión explícita del usuario: evita que cualquier reporte/dashboard
 * que ya cuenta filas de `registrations` como inscripciones reales tenga
 * que aprender a excluir filas "hijas" en todos lados.
 *
 * El diseño clave: el taller NO se agrega a la inscripción hasta que este
 * pago quede `paid` — `participantes_payload`/`totales_payload` guardan
 * exactamente lo que se le pasaría a ActualizarInscripcionPagadaAction, y
 * recién se aplica ahí cuando SIP confirma (ConfirmarPagoAdicionalAction).
 * Si el participante nunca paga, no hay nada que revertir: el cupo del
 * taller nunca se tocó.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos_adicionales_inscripcion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained('registrations')->cascadeOnDelete();
            // Alias SIP propio (ej. 'AD-XXXXXXXX') — deliberadamente distinto
            // de `registrations.referencia` para no colisionar con el
            // callback existente (que asume alias === referencia de una
            // inscripción real).
            $table->string('referencia', 30)->unique();
            $table->decimal('monto', 10, 2);
            $table->string('moneda_pago', 3)->default('BOB');
            $table->json('participantes_payload');
            $table->json('totales_payload');
            // idQr de SIP — solo para diagnóstico; a diferencia de
            // `registrations` (que nunca lo persiste, ver hallazgo de la
            // exploración), acá sí se guarda porque esta tabla es
            // justamente el lugar dedicado a trackear el intento de pago.
            $table->string('qr_id')->nullable();
            $table->enum('pago_status', ['pending', 'paid', 'expired', 'error'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index('registration_id');
            $table->index('pago_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos_adicionales_inscripcion');
    }
};

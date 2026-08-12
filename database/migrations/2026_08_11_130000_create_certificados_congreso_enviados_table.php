<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Certificados automáticos de congreso (Fase 2 de
 * PRD-Agenda-sessiones-onlycongresos.md) — ver elascenso/event/brain/
 * (sesión 11/08/2026). Idempotencia por participante+evento (un solo
 * certificado por participante, cuando el evento completo cierra, con
 * la lista de sesiones a las que asistió — no uno por sesión).
 *
 * A propósito NO se reusa `registration_notifications`
 * (NotificacionService) — esa opera por Registration completo (mismo
 * mail a todos los participantes de la inscripción) y no tiene
 * granularidad de participante ni de evento-congreso.
 *
 * La fila solo se crea si el envío tuvo éxito (ver
 * EnviarCertificadosCongresoAction) — a diferencia del patrón de
 * NotificacionService que marca "enviado" aunque el SMTP falle, un
 * fallo puntual acá se reintenta solo en la próxima corrida diaria.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificados_congreso_enviados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evento_id')->constrained('eventos')->cascadeOnDelete();
            $table->foreignId('participante_id')->constrained('participantes')->cascadeOnDelete();
            $table->timestamp('enviado_at')->useCurrent();
            $table->timestamps();

            $table->unique(['evento_id', 'participante_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificados_congreso_enviados');
    }
};

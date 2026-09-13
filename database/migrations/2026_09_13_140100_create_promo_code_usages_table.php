<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Promo code multi-uso (13/09/2026) — auditoría de cada uso individual de
 * un código. `registration_id` (FK singular en `promo_codes`) no puede
 * representar "usado por N inscripciones" — confirmado que nada fuera de
 * RegistrationService/PromoCodeController::destroy() lo lee en ningún
 * repo, así que deja de ser la fuente de autorización y esta tabla pasa a
 * ser el registro real de "quién usó qué, cuándo, por cuánto".
 *
 * `participante_id` nullable a propósito: RegistrationService::
 * consumePromoCode() se llama ANTES de crear el Participante (mismo orden
 * que hoy) — la fila nace con este campo null y se completa con un update
 * puntual apenas el participante existe (ver CrearInscripcionAction::
 * createParticipant() / RegistrationService::createParticipantFromData()).
 *
 * Sin timestamps() — mismo criterio que PromoCode ($timestamps = false);
 * `used_at` lo pone el propio servicio explícitamente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo_code_usages', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('promo_code_id');
            $table->unsignedBigInteger('registration_id');
            $table->unsignedBigInteger('participante_id')->nullable();
            $table->decimal('monto_descontado', 10, 2)->nullable();
            $table->timestamp('used_at')->nullable();

            $table->foreign('promo_code_id')->references('id')->on('promo_codes')->cascadeOnDelete();
            $table->foreign('registration_id')->references('id')->on('registrations')->cascadeOnDelete();
            $table->foreign('participante_id')->references('id')->on('participantes')->nullOnDelete();

            $table->index('promo_code_id');
            $table->index('registration_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_code_usages');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SIP multi-banco (28/08/2026) — ver
 * brain/api_rest_event/PLAN-SIP-MULTIBANCO-28082026.md. Antes se cobraba
 * por SIP con un único banco (Bisa), configurado en el .env de
 * `sip-payment-integration` — cambiar de banco era editar ese .env a
 * mano. Ahora cada organizador puede tener su propio banco SIP asignado
 * acá; un organizador sin fila acá sigue usando el .env actual como
 * default (`organizador_id` nullable, pero en la práctica cada fila real
 * SIEMPRE tiene un organizador — nullable solo para no bloquear altas
 * futuras de "banco todavía sin asignar").
 *
 * Credenciales reales — NUNCA se expone esta tabla por ningún Resource
 * público (ver FormasPagoResource, que ya evita exponer `config` de
 * métodos integrados por el mismo motivo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sip_bancos', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('organizador_id')->nullable();
            $table->string('nombre');
            $table->string('sip_username');
            $table->string('sip_password');
            $table->string('sip_apikey');
            $table->string('sip_apikey_servicio');
            // Nullable — todos los bancos de hoy usan el mismo endpoint SIP
            // de MC4; se deja configurable por si algún banco futuro usa
            // otro. Vacío = cae al default de sip-payment-integration/.env.
            $table->string('sip_base_auth_url')->nullable();
            $table->string('sip_base_api_url')->nullable();
            $table->string('callback_basic_user');
            $table->string('callback_basic_password');
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->foreign('organizador_id')->references('id')->on('organizadores')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sip_bancos');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WhatsApp Business API oficial por organizador (08/10/2026) — mismo
 * espíritu que `sip_bancos` (28/08/2026): credenciales reales de un
 * servicio externo que cada organizador trae por su cuenta, asignadas acá.
 * A diferencia de SIP, el que las USA (NotificacionService) ya vive en
 * ApiRestEvent — no hace falta ningún endpoint interno cruzando repos.
 *
 * `organizador_id` nullable por el mismo motivo que `sip_bancos`: no
 * bloquear un alta futura de una cuenta "todavía sin asignar", aunque en la
 * práctica cada fila real siempre tiene un organizador.
 *
 * `access_token` — credencial real (token de sistema de Meta Cloud API).
 * NUNCA se expone por ningún Resource (ver WhatsappCuentaResource) ni se
 * devuelve en ninguna respuesta de la API a admin-eventos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_cuentas', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('organizador_id')->nullable();
            $table->string('nombre');
            // Identificadores de Meta Cloud API — ver
            // https://developers.facebook.com/docs/whatsapp/cloud-api.
            $table->string('phone_number_id');
            $table->string('business_account_id')->nullable();
            $table->string('access_token');
            // Mensajes que inicia el negocio (no una respuesta dentro de
            // las 24h de que la persona escribió primero) tienen que usar
            // una plantilla pre-aprobada por Meta — no texto libre. Se
            // aprueba UNA plantilla genérica de utilidad por organizador,
            // con un solo parámetro de texto libre ({{1}}), para reusar el
            // mismo mensaje que ya arma NotificacionService para
            // correo/openwa/externo sin tener que aprobar una plantilla
            // distinta por cada tipo de aviso.
            $table->string('template_name')->default('notificacion_sistema');
            $table->string('template_lang')->default('es');
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->foreign('organizador_id')->references('id')->on('organizadores')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_cuentas');
    }
};

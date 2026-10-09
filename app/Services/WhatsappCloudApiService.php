<?php

namespace App\Services;

use App\Models\WhatsappCuenta;
use Illuminate\Support\Facades\Http;

/**
 * WhatsApp Business API oficial por organizador (08/10/2026) — llama a la
 * API real de Meta (WhatsApp Cloud API). Un mensaje que el negocio inicia
 * (no es una respuesta dentro de las 24h de que la persona escribió
 * primero) tiene que usar una plantilla pre-aprobada por Meta — no texto
 * libre como sí permiten openwa/externo. Se usa UNA plantilla genérica de
 * utilidad por organizador, con un único parámetro de texto libre, para
 * reusar el mismo mensaje que ya arma NotificacionService para los demás
 * canales sin tener que aprobar una plantilla distinta por cada tipo de
 * aviso (pago pendiente, confirmado, recordatorios, etc.).
 */
class WhatsappCloudApiService
{
    /**
     * @throws \RuntimeException si Meta responde con un error — el caller
     *   (SendWhatsappOficialMessageJob) decide si reintentar o no según el
     *   tipo de error.
     */
    public function enviarTemplate(WhatsappCuenta $cuenta, string $telefonoDigitos, string $textoParametro): void
    {
        $baseUrl = config('services.whatsapp_cloud.base_url', 'https://graph.facebook.com/v21.0');

        $response = Http::withToken($cuenta->access_token)
            ->timeout(15)
            ->post("{$baseUrl}/{$cuenta->phone_number_id}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $telefonoDigitos,
                'type' => 'template',
                'template' => [
                    'name' => $cuenta->template_name,
                    'language' => ['code' => $cuenta->template_lang],
                    'components' => [[
                        'type' => 'body',
                        'parameters' => [[
                            'type' => 'text',
                            'text' => $textoParametro,
                        ]],
                    ]],
                ],
            ]);

        if ($response->failed()) {
            throw new \RuntimeException(
                "WhatsApp Cloud API error [{$response->status()}] para cuenta {$cuenta->id}: {$response->body()}",
                $response->status()
            );
        }
    }
}

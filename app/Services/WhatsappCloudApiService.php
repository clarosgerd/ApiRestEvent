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
 *
 * Fix real (09/10/2026): Meta ya no acepta una variable posicional suelta
 * (`{{1}}`, sin `parameter_name`) — devuelve "La plantilla contiene
 * parámetros variables con formato incorrecto" al intentar aprobarla.
 * Ahora exige una variable CON NOMBRE (minúsculas/números/guion bajo) y
 * rechaza que quede pegada al principio o al final del cuerpo. La
 * convención de este proyecto: el cuerpo de la plantilla aprobada en Meta
 * tiene que ser `Pass2Go: {{mensaje}}\n\nGracias por confiar en nosotros.`
 * (o cualquier texto fijo antes/después de una variable llamada
 * `mensaje`) — `parameter_name` acá tiene que coincidir exactamente con
 * ese nombre.
 */
class WhatsappCloudApiService
{
    private const NOMBRE_PARAMETRO = 'mensaje';

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
                            // parameter_name (09/10/2026) — obligatorio desde
                            // que Meta dejó de aceptar variables posicionales
                            // sueltas; tiene que coincidir con el nombre de
                            // la variable de la plantilla aprobada ({{mensaje}}).
                            'parameter_name' => self::NOMBRE_PARAMETRO,
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

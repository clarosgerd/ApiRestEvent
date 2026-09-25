<?php

namespace App\Http\Controllers;

use App\Actions\EnviarSeguimientoLeadAction;
use App\Mail\SeguimientoExpositorMail;
use App\Models\EmpresaExpositora;
use App\Models\LeadCapturado;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * SmartStand fase 4 — ajustes del correo de seguimiento de UNA empresa
 * (guard `expositores`). El organizador enciende la función por evento
 * (`expositores_config.seguimiento_habilitado`); acá la empresa decide si la usa
 * y qué dice.
 *
 * Anti-abuso: el mensaje es texto plano y NO puede contener URLs ni HTML; el
 * único link posible es `seguimiento_url` (https). Así la plataforma no sirve
 * para mandar phishing con el dominio y la reputación de Inscrito.
 */
class ExpositorSeguimientoController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->presentar($request->user('expositores'))]);
    }

    public function update(Request $request): JsonResponse
    {
        $cuenta = $request->user('expositores');

        $data = $request->validate([
            'activo'      => ['required', 'boolean'],
            'asunto'      => ['nullable', 'string', 'max:150'],
            'mensaje'     => ['nullable', 'string', 'max:1500'],
            'url'         => ['nullable', 'string', 'max:500', 'url:https'],
            'reply_to'    => ['nullable', 'string', 'max:190', 'email'],
        ], [
            'activo.required' => 'Indica si quieres activar el correo de seguimiento.',
            'activo.boolean'  => 'Indica si quieres activar el correo de seguimiento.',
            'asunto.max'      => 'El asunto puede tener hasta 150 caracteres.',
            'mensaje.max'     => 'El mensaje puede tener hasta 1500 caracteres.',
            'url.url'         => 'El enlace del catálogo debe empezar con https:// (por seguridad no se aceptan enlaces http://).',
            'url.max'         => 'El enlace del catálogo es demasiado largo.',
            'reply_to.email'  => 'Escribe un correo válido para las respuestas.',
        ]);

        foreach (['asunto', 'mensaje'] as $campo) {
            $valor = (string) ($data[$campo] ?? '');
            if ($valor !== '' && $this->contieneLinkOMarcado($valor)) {
                throw ValidationException::withMessages([
                    $campo => 'No se permiten links, correos, direcciones web ni etiquetas HTML en este texto. Usa el campo "Link de catálogo" para compartir un enlace.',
                ]);
            }
        }

        $cuenta->update([
            'seguimiento_activo'   => (bool) $data['activo'],
            'seguimiento_asunto'   => $this->vacioANull($data['asunto'] ?? null),
            'seguimiento_mensaje'  => $this->vacioANull($data['mensaje'] ?? null),
            'seguimiento_url'      => $this->vacioANull($data['url'] ?? null),
            'seguimiento_reply_to' => $this->vacioANull($data['reply_to'] ?? null),
        ]);

        return response()->json(['success' => true, 'data' => $this->presentar($cuenta->fresh())]);
    }

    private function presentar(EmpresaExpositora $cuenta): array
    {
        $config = $cuenta->evento?->expositores_config ?? [];

        $porEstado = LeadCapturado::where('empresa_expositora_id', $cuenta->id)
            ->whereNotNull('seguimiento_estado')
            ->selectRaw('seguimiento_estado as estado, COUNT(*) as total')
            ->groupBy('seguimiento_estado')
            ->pluck('total', 'estado');

        return [
            'habilitadoPorEvento' => (bool) ($config['seguimiento_habilitado'] ?? false),
            'activo'              => (bool) $cuenta->seguimiento_activo,
            'asunto'              => $cuenta->seguimiento_asunto,
            'mensaje'             => $cuenta->seguimiento_mensaje,
            'url'                 => $cuenta->seguimiento_url,
            'replyTo'             => $cuenta->seguimiento_reply_to,
            'emailAcceso'         => $cuenta->email,
            'porDefecto'          => [
                'asunto'  => SeguimientoExpositorMail::ASUNTO_DEFAULT,
                'mensaje' => SeguimientoExpositorMail::MENSAJE_DEFAULT,
            ],
            'contadores'          => [
                'enviados'  => (int) ($porEstado[EnviarSeguimientoLeadAction::ESTADO_ENVIADO] ?? 0),
                'omitidos'  => (int) ($porEstado[EnviarSeguimientoLeadAction::ESTADO_OMITIDO] ?? 0),
                'fallidos'  => (int) ($porEstado[EnviarSeguimientoLeadAction::ESTADO_FALLIDO] ?? 0),
            ],
        ];
    }

    /** URLs (con o sin esquema), correos y etiquetas HTML. */
    private function contieneLinkOMarcado(string $texto): bool
    {
        return (bool) preg_match(
            '~(https?:|ftp:|www\.|//|[a-z0-9-]+\.(com|net|org|edu|gov|io|co|bo|ar|pe|cl|mx|es|info|biz|ly|me|app|dev|xyz|top|site|online|link|click|shop|store|cc|tv|us)\b|@|<[^>]*>|[<>])~i',
            $texto,
        );
    }

    private function vacioANull(?string $valor): ?string
    {
        $valor = trim((string) $valor);

        return $valor === '' ? null : $valor;
    }
}

<?php

namespace App\Mail;

use App\Models\LeadCapturado;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * SmartStand fase 4 — correo de seguimiento de una empresa expositora a un
 * asistente capturado. El texto lo escribe la empresa, así que:
 *  - es SOLO texto plano (se escapa y se convierte en párrafos; jamás HTML),
 *  - la validación de ExpositorSeguimientoController impide URLs dentro del texto
 *    (el único link permitido es `seguimiento_url`, https, mostrado aparte),
 *  - sale desde MAIL_FROM_ADDRESS (SPF/DKIM del dominio) con el nombre de la
 *    empresa, y las respuestas van al Reply-To de la empresa,
 *  - siempre lleva quién lo envía, el evento y el link firmado de baja.
 * No se pone en cola: lo despacha EnviarSeguimientoLeadJob con afterResponse().
 */
class SeguimientoExpositorMail extends Mailable
{
    use Queueable;

    public const ASUNTO_DEFAULT = 'Gracias por visitar nuestro stand';

    public const MENSAJE_DEFAULT = "Hola {nombre},\n\nFue un gusto atenderte en el stand de {empresa}. Gracias por visitarnos: quedamos atentos a cualquier consulta que quieras hacernos.\n\nUn saludo cordial.";

    public function __construct(public LeadCapturado $lead)
    {
    }

    public function build(): self
    {
        $empresa = $this->lead->empresa;
        $evento = $empresa->evento;
        $participante = $this->lead->participante;

        $nombreEmpresa = $this->sinSaltos((string) $empresa->nombre);
        $asunto = $this->sinSaltos(self::reemplazar(
            $empresa->seguimiento_asunto ?: self::ASUNTO_DEFAULT,
            $participante->nombre,
            $nombreEmpresa,
        ));

        $mail = $this->from(config('mail.from.address'), $nombreEmpresa . ' vía Inscrito')
            ->subject($asunto)
            ->view('emails.seguimiento-expositor')
            ->with([
                'parrafos'     => $this->parrafos(self::reemplazar(
                    $empresa->seguimiento_mensaje ?: self::MENSAJE_DEFAULT,
                    $participante->nombre,
                    $nombreEmpresa,
                )),
                'empresa'      => $nombreEmpresa,
                'evento'       => $evento?->nombre,
                'catalogoUrl'  => $empresa->seguimiento_url,
                'bajaUrl'      => URL::signedRoute('seguimiento.baja', ['lead' => $this->lead->id]),
            ]);

        $replyTo = $empresa->seguimiento_reply_to ?: $empresa->email;
        if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $mail->replyTo($replyTo, $nombreEmpresa);
        }

        return $mail;
    }

    public static function reemplazar(string $texto, ?string $nombre, string $empresa): string
    {
        return str_replace(['{nombre}', '{empresa}'], [trim((string) $nombre) ?: 'colega', $empresa], $texto);
    }

    /** @return array<int, string> párrafos ya listos para escapar en la vista */
    private function parrafos(string $texto): array
    {
        $texto = Str::of($texto)->replace(["\r\n", "\r"], "\n")->toString();

        return array_values(array_filter(
            array_map('trim', preg_split('/\n{2,}/', $texto) ?: []),
            fn ($p) => $p !== '',
        ));
    }

    /** Un encabezado de correo no puede llevar saltos de línea (inyección de headers). */
    private function sinSaltos(string $valor): string
    {
        return trim((string) preg_replace('/[\r\n]+/', ' ', $valor));
    }
}

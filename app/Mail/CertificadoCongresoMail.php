<?php

namespace App\Mail;

use App\Models\Evento;
use App\Models\Participante;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

/**
 * Certificado automático de congreso — ver
 * EnviarCertificadosCongresoAction y elascenso/event/brain/ (sesión
 * 11/08/2026). Un solo certificado por participante por evento, con la
 * lista de sesiones a las que asistió (no una por sesión). El PDF
 * adjunto reusa `tickets/certificados.blade.php` (mismo patrón de
 * `PagoConfirmadoMail::build()` con `tickets/eticket.blade.php`), con un
 * único item de tipo `asistencia_congreso` nuevo.
 */
class CertificadoCongresoMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Evento $evento,
        public Participante $participante,
        public Collection $sesiones,
    ) {
    }

    public function build(): self
    {
        $mail = $this->subject("Certificado de asistencia — {$this->evento->nombre}")
            ->view('emails.certificado-congreso')
            ->with([
                'evento' => $this->evento,
                'participante' => $this->participante,
                'sesiones' => $this->sesiones,
            ]);

        // Título (20/08/2026) — mismo criterio que EventoController::
        // certificadosPdf(), ver el comentario ahí.
        $item = [
            'tipo' => 'asistencia_congreso',
            'nombre' => collect([$this->participante->alias, $this->participante->nombre, $this->participante->apellido])
                ->filter()->implode(' '),
            'sesiones' => $this->sesiones->map(fn ($s) => [
                'titulo' => $s->titulo,
                'ponente' => $s->ponente,
                'sala' => $s->sala,
                'fecha' => $s->fecha,
            ])->all(),
            'referencia' => $this->participante->registration?->referencia ?? '',
        ];

        $pdf = Pdf::loadView('tickets.certificados', [
            'evento' => $this->evento,
            'items' => [$item],
            'logo' => $this->logoDataUri($this->evento->imagen_portada_url),
            'brand' => $this->safeHex($this->evento->color_hex),
        ])->setPaper('a4', 'landscape');

        return $mail->attachData(
            $pdf->output(),
            "certificado-{$this->evento->id}-{$this->participante->id}.pdf",
            ['mime' => 'application/pdf']
        );
    }

    /**
     * Duplicado a propósito de EventoController::logoDataUri() (privado
     * ahí, sin punto de extensión compartido) — mismo comportamiento:
     * descarga el logo del evento y lo convierte a data URI para que
     * dompdf lo pueda incrustar sin depender de acceso de red al
     * renderizar. Si falla, el certificado sale sin logo, no rompe el
     * envío.
     */
    private function logoDataUri(?string $url): ?string
    {
        if (empty($url)) {
            return null;
        }

        try {
            $response = Http::timeout(5)->get($url);
            $mime = $response->header('Content-Type') ?: '';

            if (! $response->successful() || ! str_starts_with($mime, 'image/')) {
                return null;
            }

            return 'data:'.$mime.';base64,'.base64_encode($response->body());
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Duplicado a propósito de EventoController::safeHex() — mismo
     * comportamiento y mismo color de fallback.
     */
    private function safeHex(?string $hex, string $fallback = '#022858'): string
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', (string) $hex) ? $hex : $fallback;
    }
}

<?php

namespace App\Mail;

use App\Models\Evento;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RecordatorioDashboardOrganizadorMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Evento $evento,
        public string $dashboardUrl,
    ) {
    }

    public function build(): self
    {
        return $this->subject("Tu evento \"{$this->evento->nombre}\" empieza en 15 días — dashboard de inscripciones")
            ->view('emails.recordatorio-dashboard-organizador')
            ->with([
                'evento'       => $this->evento,
                'dashboardUrl' => $this->dashboardUrl,
            ]);
    }
}

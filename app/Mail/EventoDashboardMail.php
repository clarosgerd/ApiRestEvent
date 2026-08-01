<?php

namespace App\Mail;

use App\Models\Evento;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EventoDashboardMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Evento $evento,
        public string $dashboardUrl,
        public ?string $deliveryUrl = null,
    ) {
    }

    public function build(): self
    {
        return $this->subject("Dashboard de inscripciones — {$this->evento->nombre}")
            ->view('emails.evento-dashboard')
            ->with([
                'evento'       => $this->evento,
                'dashboardUrl' => $this->dashboardUrl,
                'deliveryUrl'  => $this->deliveryUrl,
            ]);
    }
}

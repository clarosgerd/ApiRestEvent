<?php

namespace App\Mail;

use App\Models\Persona;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class MarketingEventoMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param Collection<int, \App\Models\Evento> $eventos
     */
    public function __construct(
        public Persona $persona,
        public Collection $eventos
    ) {
    }

    public function build(): self
    {
        $optOutUrl = URL::signedRoute('marketing.opt-out', ['persona' => $this->persona->id]);

        return $this->subject('Events you might like')
            ->view('emails.marketing-evento')
            ->with([
                'persona'   => $this->persona,
                'eventos'   => $this->eventos,
                'optOutUrl' => $optOutUrl,
            ]);
    }
}

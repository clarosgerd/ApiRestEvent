<?php

namespace App\Mail;

use App\Models\Evento;
use App\Models\FormType;
use App\Models\ListaEspera;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Aviso de promoción de lista de espera — ver
 * PRD-kit-tallas-stock-lista-espera.md y App\Actions\PromoverListaEsperaAction.
 * A propósito no lleva adjunto ni reserva nada — solo notifica que hay
 * lugar; es primero-en-llegar-primero-en-servir después del aviso (ver
 * la decisión de diseño del PRD).
 */
class ListaEsperaPromovidaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Evento $evento,
        public FormType $formType,
        public ListaEspera $entry,
    ) {
    }

    public function build(): self
    {
        return $this->subject("¡Ya hay lugar! — {$this->evento->nombre}")
            ->view('emails.lista-espera-promovida')
            ->with([
                'evento'    => $this->evento,
                'formType'  => $this->formType,
                'entry'     => $this->entry,
            ]);
    }
}

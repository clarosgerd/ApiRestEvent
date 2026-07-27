<?php

namespace App\Mail;

use App\Models\Registration;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RecordatorioPagoMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param int $tier 1 = primer aviso (30 días), 2 = segundo aviso (15
     *                  días, con advertencia de reversión de cupo)
     */
    public function __construct(
        public Registration $registration,
        public int $tier,
        public int $diasGracia
    ) {
    }

    public function build(): self
    {
        $evento = $this->registration->evento;

        $subject = $this->tier === 1
            ? "Payment Reminder — {$this->registration->evento_nombre} [{$this->registration->referencia}]"
            : "Urgent: Payment Reminder — {$this->registration->evento_nombre} [{$this->registration->referencia}]";

        return $this->subject($subject)
            ->view('emails.recordatorio-pendiente')
            ->with([
                'registration' => $this->registration,
                'evento'       => $evento,
                'tier'         => $this->tier,
                'diasGracia'   => $this->diasGracia,
            ]);
    }
}

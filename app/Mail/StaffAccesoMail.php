<?php

namespace App\Mail;

use App\Models\Registration;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Alta de staff (02/10/2026) — reemplaza a PagoConfirmadoMail para una
 * inscripción con form_type.es_staff=true: no tiene sentido adjuntarle un
 * e-ticket de "pagado" a alguien que no pagó nada (ver
 * NotificacionService::notificarPagoConfirmado()). Avisa que ya puede
 * usar la app de staff (descarga offline de participantes + check-in) con
 * su cuenta de Persona — el correo/documento de inscripción, mismo
 * usuario/contraseña que RegistrationService::syncPersonas() ya le crea
 * siempre (password = Hash::make(numero_documento)).
 */
class StaffAccesoMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Registration $registration)
    {
    }

    public function build(): self
    {
        $participante = $this->registration->participants->first();

        return $this->subject("Acceso de staff — {$this->registration->evento_nombre} [{$this->registration->referencia}]")
            ->view('emails.staff-acceso')
            ->with([
                'registration' => $this->registration,
                'evento'       => $this->registration->evento,
                'participante' => $participante,
            ]);
    }
}

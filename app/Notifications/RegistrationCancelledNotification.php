<?php

namespace App\Notifications;

use App\Models\Registration;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\Messages\WhatsAppMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RegistrationCancelledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Registration $registration,
    ) {}

    public function via(object $notifiable): array
    {
        return $this->resolveChannels();
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Inscripción cancelada - ' . $this->registration->evento_nombre)
            ->line('Tu inscripción ha sido cancelada.')
            ->line('Referencia: ' . $this->registration->referencia)
            ->line('Evento: ' . $this->registration->evento_nombre)
            ->line('Si tienes preguntas, contacta al organizador.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'       => 'registration_cancelled',
            'title'      => 'Inscripción cancelada',
            'message'    => 'Tu inscripción para ' . $this->registration->evento_nombre . ' ha sido cancelada.',
            'referencia' => $this->registration->referencia,
            'evento'     => $this->registration->evento_nombre,
        ];
    }

    public function toWhatsApp(object $notifiable): WhatsAppMessage
    {
        return WhatsAppMessage::create(
            "❌ Inscripción cancelada\n" .
            "📌 Evento: {$this->registration->evento_nombre}\n" .
            "🔢 Referencia: {$this->registration->referencia}\n" .
            "Si tienes preguntas, contacta al organizador."
        );
    }

    private function resolveChannels(): array
    {
        $channels = [];

        if (config('notifications.channels.mail')) {
            $channels[] = 'mail';
        }

        if (config('notifications.channels.database')) {
            $channels[] = 'database';
        }

        if (config('notifications.channels.whatsapp')) {
            $channels[] = WhatsAppChannel::class;
        }

        return $channels;
    }
}

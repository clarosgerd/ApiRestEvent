<?php

namespace App\Notifications;

use App\Models\Evento;
use App\Models\Registration;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\Messages\WhatsAppMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EventReminderNotification extends Notification implements ShouldQueue
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
        $evento = $this->registration->evento;

        return (new MailMessage)
            ->subject('Recordatorio - ' . $evento->nombre)
            ->line('Tu evento comienza pronto.')
            ->line('Evento: ' . $evento->nombre)
            ->line('Fecha: ' . $evento->fecha_inicio->format('d/m/Y H:i'))
            ->line('Lugar: ' . ($evento->lugar ?? 'Por definir'))
            ->line('Referencia: ' . $this->registration->referencia);
    }

    public function toArray(object $notifiable): array
    {
        $evento = $this->registration->evento;

        return [
            'type'       => 'event_reminder',
            'title'      => 'Recordatorio de evento',
            'message'    => 'Tu evento "' . $evento->nombre . ' comienza pronto.',
            'evento'     => $evento->nombre,
            'fecha'      => $evento->fecha_inicio?->format('d/m/Y H:i'),
            'lugar'      => $evento->lugar,
            'referencia' => $this->registration->referencia,
        ];
    }

    public function toWhatsApp(object $notifiable): WhatsAppMessage
    {
        $evento = $this->registration->evento;

        return WhatsAppMessage::create(
            "⏰ Recordatorio de evento\n" .
            "📌 Evento: {$evento->nombre}\n" .
            "📅 Fecha: " . $evento->fecha_inicio->format('d/m/Y H:i') . "\n" .
            "📍 Lugar: " . ($evento->lugar ?? 'Por definir') . "\n" .
            "🔢 Referencia: {$this->registration->referencia}"
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

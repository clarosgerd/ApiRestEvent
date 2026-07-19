<?php

namespace App\Notifications;

use App\Models\Registration;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\Messages\WhatsAppMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RegistrationCreatedNotification extends Notification implements ShouldQueue
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
        $total = $this->registration->totals->grand_total ?? 0;

        return (new MailMessage)
            ->subject('Inscripción registrada - ' . $this->registration->evento_nombre)
            ->line('Tu inscripción ha sido registrada exitosamente.')
            ->line('Referencia: ' . $this->registration->referencia)
            ->line('Evento: ' . $this->registration->evento_nombre)
            ->line('Total: Bs. ' . number_format($total, 2))
            ->line('Estado del pago: Pendiente');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'       => 'registration_created',
            'title'      => 'Inscripción registrada',
            'message'    => 'Tu inscripción para ' . $this->registration->evento_nombre . ' ha sido registrada.',
            'referencia' => $this->registration->referencia,
            'evento'     => $this->registration->evento_nombre,
            'total'      => $this->registration->totals->grand_total ?? 0,
        ];
    }

    public function toWhatsApp(object $notifiable): WhatsAppMessage
    {
        $total = $this->registration->totals->grand_total ?? 0;

        return WhatsAppMessage::create(
            "✅ Inscripción registrada\n" .
            "📌 Evento: {$this->registration->evento_nombre}\n" .
            "🔢 Referencia: {$this->registration->referencia}\n" .
            "💰 Total: Bs. " . number_format($total, 2) . "\n" .
            "⏳ Estado: Pendiente de pago"
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

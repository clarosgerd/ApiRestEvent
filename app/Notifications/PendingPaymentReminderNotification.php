<?php

namespace App\Notifications;

use App\Models\Registration;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\Messages\WhatsAppMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PendingPaymentReminderNotification extends Notification implements ShouldQueue
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
        $evento = $this->registration->evento;

        return (new MailMessage)
            ->subject('Pago pendiente - ' . $evento->nombre)
            ->line('Tu inscripción tiene un pago pendiente.')
            ->line('Referencia: ' . $this->registration->referencia)
            ->line('Evento: ' . $evento->nombre)
            ->line('Total a pagar: Bs. ' . number_format($total, 2))
            ->line('Fecha del evento: ' . $evento->fecha_inicio->format('d/m/Y'))
            ->line('Por favor completa tu pago antes del evento.');
    }

    public function toArray(object $notifiable): array
    {
        $total = $this->registration->totals->grand_total ?? 0;
        $evento = $this->registration->evento;

        return [
            'type'       => 'pending_payment_reminder',
            'title'      => 'Pago pendiente',
            'message'    => 'Tu pago para "' . $evento->nombre . '" está pendiente. Total: Bs. ' . number_format($total, 2),
            'referencia' => $this->registration->referencia,
            'evento'     => $evento->nombre,
            'total'      => $total,
            'fecha_evento' => $evento->fecha_inicio?->format('d/m/Y'),
        ];
    }

    public function toWhatsApp(object $notifiable): WhatsAppMessage
    {
        $total = $this->registration->totals->grand_total ?? 0;
        $evento = $this->registration->evento;

        return WhatsAppMessage::create(
            "⚠️ Pago pendiente\n" .
            "📌 Evento: {$evento->nombre}\n" .
            "🔢 Referencia: {$this->registration->referencia}\n" .
            "💰 Total: Bs. " . number_format($total, 2) . "\n" .
            "📅 Fecha evento: " . $evento->fecha_inicio->format('d/m/Y') . "\n" .
            "Por favor completa tu pago antes del evento."
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

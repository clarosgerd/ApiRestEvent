<?php

namespace App\Notifications;

use App\Models\Registration;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\Messages\WhatsAppMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentConfirmedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Registration $registration,
        public readonly float $costoAdicion = 0,
    ) {}

    public function via(object $notifiable): array
    {
        return $this->resolveChannels();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $total = $this->registration->totals->grand_total ?? 0;

        return (new MailMessage)
            ->subject('Pago confirmado - ' . $this->registration->evento_nombre)
            ->line('Tu pago ha sido confirmado exitosamente.')
            ->line('Referencia: ' . $this->registration->referencia)
            ->line('Evento: ' . $this->registration->evento_nombre)
            ->line('Total pagado: Bs. ' . number_format($total, 2))
            ->line('Estado: Pagado');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'         => 'payment_confirmed',
            'title'        => 'Pago confirmado',
            'message'      => 'Tu pago para ' . $this->registration->evento_nombre . ' ha sido confirmado.',
            'referencia'   => $this->registration->referencia,
            'evento'       => $this->registration->evento_nombre,
            'total'        => $this->registration->totals->grand_total ?? 0,
            'costo_adicion' => $this->costoAdicion,
        ];
    }

    public function toWhatsApp(object $notifiable): WhatsAppMessage
    {
        $total = $this->registration->totals->grand_total ?? 0;

        return WhatsAppMessage::create(
            "💰 Pago confirmado\n" .
            "📌 Evento: {$this->registration->evento_nombre}\n" .
            "🔢 Referencia: {$this->registration->referencia}\n" .
            "💵 Total pagado: Bs. " . number_format($total, 2) . "\n" .
            "✅ Estado: Pagado"
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

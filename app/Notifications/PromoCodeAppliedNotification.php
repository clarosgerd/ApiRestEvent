<?php

namespace App\Notifications;

use App\Models\PromoCode;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\Messages\WhatsAppMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PromoCodeAppliedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly PromoCode $promoCode,
        public readonly float $newTotal,
    ) {}

    public function via(object $notifiable): array
    {
        return $this->resolveChannels();
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'       => 'promo_code_applied',
            'title'      => 'Código promocional aplicado',
            'message'    => "El código {$this->promoCode->promo_code} ha sido aplicado. Descuento: {$this->promoCode->descuento}%",
            'codigo'     => $this->promoCode->promo_code,
            'descuento'  => $this->promoCode->descuento,
            'nuevo_total' => $this->newTotal,
        ];
    }

    public function toWhatsApp(object $notifiable): WhatsAppMessage
    {
        return WhatsAppMessage::create(
            "🏷️ Código promocional aplicado\n" .
            "🔑 Código: {$this->promoCode->promo_code}\n" .
            "📉 Descuento: {$this->promoCode->descuento}%\n" .
            "💰 Nuevo total: Bs. " . number_format($this->newTotal, 2)
        );
    }

    private function resolveChannels(): array
    {
        $channels = [];

        if (config('notifications.channels.database')) {
            $channels[] = 'database';
        }

        if (config('notifications.channels.whatsapp')) {
            $channels[] = WhatsAppChannel::class;
        }

        return $channels;
    }
}

<?php

namespace App\Notifications\Channels;

use App\Jobs\SendWhatsappMessageJob;
use Illuminate\Notifications\Notification;

class WhatsAppChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (!config('notifications.channels.whatsapp')) {
            return;
        }

        $chatId = $this->resolveChatId($notifiable);

        if (!$chatId) {
            return;
        }

        $message = $notification->toWhatsApp($notifiable);

        if ($message && !empty($message->content)) {
            dispatch(new SendWhatsappMessageJob($chatId, $message->content));
        }
    }

    private function resolveChatId(object $notifiable): ?string
    {
        $phone = $notifiable->celular
            ?? $notifiable->telefono
            ?? null;

        if (!$phone) {
            return null;
        }

        $phone = preg_replace('/[^0-9]/', '', $phone);

        $countryCode = config('notifications.whatsapp.country_code', '591');

        if (!str_starts_with($phone, $countryCode)) {
            $phone = $countryCode . $phone;
        }

        return $phone . '@c.us';
    }
}

<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Notification Channels
    |--------------------------------------------------------------------------
    |
    | Enable or disable each notification channel per environment.
    |
    */
    'channels' => [
        'mail'     => env('NOTIFICATION_MAIL_ENABLED', true),
        'database' => env('NOTIFICATION_DATABASE_ENABLED', true),
        'whatsapp' => env('NOTIFICATION_WHATSAPP_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Reminder Schedules (in days before event)
    |--------------------------------------------------------------------------
    |
    | How many days before the event to send each reminder.
    |
    */
    'reminders' => [
        'event_reminder_days'  => (int) env('EVENT_REMINDER_DAYS', 1),
        'pending_payment_days' => (int) env('PENDING_PAYMENT_REMINDER_DAYS', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Configuration
    |--------------------------------------------------------------------------
    |
    | Country code prepended to phone numbers when building the chatId.
    |
    */
    'whatsapp' => [
        'country_code' => env('WHATSAPP_COUNTRY_CODE', '591'),
    ],

];

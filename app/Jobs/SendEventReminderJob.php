<?php

namespace App\Jobs;

use App\Models\Evento;
use App\Models\Registration;
use App\Notifications\EventReminderNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;
use Carbon\Carbon;

class SendEventReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function handle(): void
    {
        $days = config('notifications.reminders.event_reminder_days', 1);

        $targetDate = Carbon::now()->addDays($days)->startOfDay();
        $targetDateEnd = $targetDate->copy()->endOfDay();

        $eventos = Evento::whereDate('fecha_inicio', $targetDate)
            ->where('fecha_inicio', '>=', $targetDate)
            ->where('fecha_inicio', '<=', $targetDateEnd)
            ->get();

        foreach ($eventos as $evento) {
            $registrations = Registration::with('participants')
                ->where('evento_id', $evento->id)
                ->where('pago_status', 'paid')
                ->get();

            foreach ($registrations as $registration) {
                $participants = $registration->load('participants')->participants;

                foreach ($participants as $participant) {
                    $persona = \App\Models\Persona::where('numero_documento', $participant->numero_documento)
                        ->orWhere('email', $participant->correo)
                        ->first();

                    if ($persona) {
                        Notification::send($persona, new EventReminderNotification($registration));
                    }
                }

                Notification::send($registration, new EventReminderNotification($registration));
            }
        }
    }
}

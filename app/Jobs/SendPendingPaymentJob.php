<?php

namespace App\Jobs;

use App\Models\Evento;
use App\Models\Persona;
use App\Models\Registration;
use App\Notifications\PendingPaymentReminderNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;
use Carbon\Carbon;

class SendPendingPaymentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function handle(): void
    {
        $days = config('notifications.reminders.pending_payment_days', 3);

        $targetDate = Carbon::now()->addDays($days)->startOfDay();
        $targetDateEnd = $targetDate->copy()->endOfDay();

        $eventos = Evento::whereDate('fecha_inicio', $targetDate)
            ->where('fecha_inicio', '>=', $targetDate)
            ->where('fecha_inicio', '<=', $targetDateEnd)
            ->get();

        foreach ($eventos as $evento) {
            $registrations = Registration::with('participants')
                ->where('evento_id', $evento->id)
                ->where('pago_status', 'pending')
                ->whereNull('pending_payment_notified_at')
                ->get();

            foreach ($registrations as $registration) {
                $participants = $registration->load('participants')->participants;

                foreach ($participants as $participant) {
                    $persona = Persona::where('numero_documento', $participant->numero_documento)
                        ->orWhere('email', $participant->correo)
                        ->first();

                    if ($persona) {
                        Notification::send($persona, new PendingPaymentReminderNotification($registration));
                    }
                }

                Notification::send($registration, new PendingPaymentReminderNotification($registration));

                $registration->update(['pending_payment_notified_at' => now()]);
            }
        }
    }
}

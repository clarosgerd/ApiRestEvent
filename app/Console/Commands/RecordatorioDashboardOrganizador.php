<?php

namespace App\Console\Commands;

use App\Mail\RecordatorioDashboardOrganizadorMail;
use App\Models\Evento;
use App\Models\EventoNotification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class RecordatorioDashboardOrganizador extends Command
{
    protected $signature = 'notificaciones:recordatorio-dashboard-organizador';

    protected $description = 'A 15 días del inicio de un evento "open", manda al organizador (y a los admins/superadmin) el link de su dashboard de inscripciones.';

    private const DIAS_AVISO = 15;
    private const TIPO       = 'recordatorio_dashboard_organizador';

    public function handle(): int
    {
        $enviados = 0;

        Evento::where('estado_evento_id', 'open')
            ->whereNotNull('fecha_inicio')
            ->with('organizador')
            ->chunkById(100, function ($eventos) use (&$enviados) {
                foreach ($eventos as $evento) {
                    // Carbon 3 devuelve float en diffInDays() — se castea a int
                    // antes de comparar (los días son siempre un número entero).
                    $diasHastaEvento = (int) now()->startOfDay()->diffInDays(
                        Carbon::parse($evento->fecha_inicio)->startOfDay(),
                        false
                    );

                    if ($diasHastaEvento !== self::DIAS_AVISO) {
                        continue;
                    }

                    if ($this->yaEnviado($evento)) {
                        continue;
                    }

                    $this->enviar($evento);
                    $enviados++;
                }
            });

        $this->info("Avisos de dashboard (15 días) enviados: {$enviados}");

        return self::SUCCESS;
    }

    private function yaEnviado(Evento $evento): bool
    {
        return EventoNotification::where('evento_id', $evento->id)
            ->where('tipo', self::TIPO)
            ->where('canal', 'email')
            ->exists();
    }

    private function enviar(Evento $evento): void
    {
        $dashboardUrl = URL::signedRoute('organizador.dashboard', ['evento' => $evento->id]);

        $destinatarios = collect();
        if ($evento->organizador && $evento->organizador->email) {
            $destinatarios->push($evento->organizador->email);
        }
        $destinatarios = $destinatarios
            ->merge(User::whereIn('role', ['admin', 'superadmin'])->pluck('email'))
            ->filter()
            ->unique();

        foreach ($destinatarios as $correo) {
            try {
                // Instancia nueva por destinatario — reusar la misma Mailable
                // en varios Mail::to()->send() acumula destinatarios (Mailable::to()
                // hace append, no replace), exponiendo cada correo a los demás.
                Mail::to($correo)->send(new RecordatorioDashboardOrganizadorMail($evento, $dashboardUrl));
            } catch (\Throwable $e) {
                // Una falla de SMTP en un destinatario no debe frenar el resto
                // (ni el resto de eventos que procese este mismo comando).
                Log::error("No se pudo enviar recordatorio de dashboard a {$correo} para evento #{$evento->id}: {$e->getMessage()}");
            }
        }

        EventoNotification::create([
            'evento_id'  => $evento->id,
            'tipo'       => self::TIPO,
            'canal'      => 'email',
            'enviado_at' => now(),
        ]);
    }
}

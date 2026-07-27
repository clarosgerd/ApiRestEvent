<?php

namespace App\Console\Commands;

use App\Mail\MarketingEventoMail;
use App\Models\Evento;
use App\Models\Participante;
use App\Models\Persona;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MarketingMensual extends Command
{
    protected $signature = 'notificaciones:marketing-mensual';

    protected $description = 'Envía un correo mensual con eventos publicados de los tipos que cada persona ya frecuentó (§2.6). Corre diario, filtra internamente por el día del mes configurado.';

    public function handle(): int
    {
        $enviados = 0;

        Persona::where('acepta_marketing', true)
            ->chunkById(100, function ($personas) use (&$enviados) {
                foreach ($personas as $persona) {
                    if ($this->procesarPersona($persona)) {
                        $enviados++;
                    }
                }
            });

        $this->info("Correos de marketing enviados: {$enviados}");

        return self::SUCCESS;
    }

    private function procesarPersona(Persona $persona): bool
    {
        // Ya se le mandó este mes — no reenviar aunque el cron corra de más.
        if ($persona->ultimo_envio_marketing_at?->isSameMonth(now())) {
            return false;
        }

        $participaciones = Participante::where('numero_documento', $persona->numero_documento)
            ->orWhere('correo', $persona->correo)
            ->with('registration.evento.organizador')
            ->get();

        $registrations = $participaciones->pluck('registration')->filter();
        $eventosParticipados = $registrations->pluck('evento')->filter();

        // "Gustos" inferidos del historial real — sin historial no hay nada
        // que recomendar todavía.
        $tiposEventoIds = $eventosParticipados->pluck('tipo_evento_id')->filter()->unique();
        if ($tiposEventoIds->isEmpty()) {
            return false;
        }

        // El día de envío lo define el organizador del evento más reciente
        // en el que participó — una persona puede haber participado con
        // varios organizadores; se usa el más reciente como simplificación
        // (ver brain/PLAN-NOTIFICACIONES.md §2.6).
        $organizadorMasReciente = $registrations->sortByDesc('fecha')->first()?->evento?->organizador;
        $diaEnvio = $organizadorMasReciente?->dia_envio_marketing ?? 15;

        if (now()->day !== $diaEnvio) {
            return false;
        }

        $eventosRecomendados = Evento::where('publicado', true)
            ->whereIn('tipo_evento_id', $tiposEventoIds)
            ->whereNotIn('id', $eventosParticipados->pluck('id')->unique())
            ->where('fecha_inicio', '>', now())
            ->get();

        if ($eventosRecomendados->isEmpty()) {
            return false;
        }

        $destinatario = $persona->correo ?: $persona->email;
        if (empty($destinatario)) {
            return false;
        }

        try {
            Mail::to($destinatario)->send(new MarketingEventoMail($persona, $eventosRecomendados));
        } catch (\Throwable $e) {
            Log::error("No se pudo enviar el correo de marketing a {$destinatario}: {$e->getMessage()}");
            return false;
        }

        $persona->update(['ultimo_envio_marketing_at' => now()]);

        return true;
    }
}

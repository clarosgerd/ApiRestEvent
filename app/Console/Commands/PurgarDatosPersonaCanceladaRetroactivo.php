<?php

namespace App\Console\Commands;

use App\Actions\PurgarDatosPersonaCanceladaAction;
use App\Models\Evento;
use App\Models\Participante;
use App\Models\Persona;
use App\Models\Registration;
use Illuminate\Console\Command;

/**
 * Purga retroactiva de Persona/Participante en inscripciones canceladas
 * (01/09/2026) — PurgarDatosPersonaCanceladaAction solo se dispara hacia
 * adelante, desde el momento en que una inscripción PASA a `cancelled`
 * (decisión explícita del usuario al implementar esa feature, no
 * retroactivo). Este comando aplica el MISMO criterio a mano sobre
 * inscripciones que ya estaban `cancelled` de antes, para un evento
 * puntual — pedido del usuario tras ver varias inscripciones de
 * Multipago canceladas por expiración (abandono real de pago, no un
 * bug) en un evento con `mantener_datos_persona=false`.
 *
 * Por default corre en modo reporte (no borra nada) — usar --confirmar
 * para borrar en serio, después de revisar el reporte. Pensado para
 * correr una sola vez vía Cron Jobs de cPanel (no hay SSH), no queda
 * programado como tarea recurrente.
 */
class PurgarDatosPersonaCanceladaRetroactivo extends Command
{
    protected $signature = 'personas:purgar-canceladas-retroactivo
        {evento : ID del evento a procesar}
        {--confirmar : Sin esto, solo muestra qué se borraría (dry-run)}';

    protected $description = 'Purga (o reporta) Persona/Participante de inscripciones ya canceladas de un evento con mantener_datos_persona=false';

    public function handle(PurgarDatosPersonaCanceladaAction $action): int
    {
        $eventoId = (int) $this->argument('evento');
        $confirmar = (bool) $this->option('confirmar');

        $evento = Evento::find($eventoId);
        if (!$evento) {
            $this->error("Evento {$eventoId} no existe.");

            return self::FAILURE;
        }
        if ($evento->mantener_datos_persona) {
            $this->error("El evento {$eventoId} tiene 'Mantener datos de persona' activado — no se purga nada. Destildalo primero en admin-eventos si es lo que querés.");

            return self::FAILURE;
        }

        $registrations = Registration::where('evento_id', $eventoId)
            ->where('pago_status', 'cancelled')
            ->with('participants.resultado')
            ->get();

        if ($registrations->isEmpty()) {
            $this->info('No hay inscripciones canceladas en este evento.');

            return self::SUCCESS;
        }

        $this->info(($confirmar ? 'BORRANDO' : 'REPORTE (dry-run, nada se borra todavía)') . " — evento {$eventoId}, {$registrations->count()} inscripciones canceladas.");
        $this->newLine();

        $totalParticipantes = 0;
        $totalPersonas = 0;

        foreach ($registrations as $registration) {
            foreach ($registration->participants as $participante) {
                if ($participante->resultado) {
                    $this->line("  [SKIP] {$registration->referencia} — {$participante->nombre} {$participante->apellido} tiene un resultado cargado, no se toca.");
                    continue;
                }

                // Mismo criterio que PurgarDatosPersonaCanceladaAction:
                // vigente = tiene otra inscripción (en cualquier evento)
                // que no sea cancelled/failed.
                $tieneOtraValida = Participante::where(function ($q) use ($participante) {
                    $q->where('numero_documento', $participante->numero_documento)
                        ->orWhere('correo', $participante->correo);
                })
                    ->whereHas('registration', fn ($q) => $q->whereNotIn('pago_status', ['cancelled', 'failed']))
                    ->exists();

                $personaExiste = Persona::where('numero_documento', $participante->numero_documento)
                    ->orWhere('email', $participante->correo)
                    ->exists();

                $accionPersona = $tieneOtraValida
                    ? 'NO se borra (tiene otra inscripción vigente en otro evento)'
                    : ($personaExiste ? 'se borra' : 'no existe / ya estaba borrada');

                $this->line(sprintf(
                    '  %s — %s %s (doc %s): participante se borra | persona: %s',
                    $registration->referencia,
                    $participante->nombre,
                    $participante->apellido,
                    $participante->numero_documento,
                    $accionPersona
                ));

                $totalParticipantes++;
                if (!$tieneOtraValida && $personaExiste) {
                    $totalPersonas++;
                }
            }

            if ($confirmar) {
                $action->handle($registration);
            }
        }

        $this->newLine();
        $this->info(sprintf(
            'Total: %d participante(s), %d cuenta(s) Persona %s.',
            $totalParticipantes,
            $totalPersonas,
            $confirmar ? 'borradas' : 'que se borrarían con --confirmar'
        ));

        return self::SUCCESS;
    }
}

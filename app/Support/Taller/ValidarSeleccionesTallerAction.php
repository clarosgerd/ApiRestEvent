<?php

namespace App\Support\Taller;

use App\DTOs\ParticipantDTO;
use App\DTOs\RegistrationDTO;
use App\Models\Evento;
use App\Models\ParticipanteTallerSesion;
use App\Models\SesionCongreso;
use App\Models\Taller;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Validación de las selecciones de talleres (sesiones de congreso con
 * `taller_id`) que un participante quiere agregar a una inscripción.
 * Ver brain/PLAN-CONGRESOS-TALLERES-HORARIOS-IMPLEMENTACION.md §1.5.
 *
 * Reglas (todas `DomainException` con mensaje legible para que el
 * controller mapee a 422 limpio, igual que el resto del código de
 * `CrearInscripcionAction`):
 *  - Pertenencia: la sesión pertenece al evento, el taller pertenece al
 *    evento, la sesión y el taller están activos.
 *  - Duplicado: no se puede seleccionar dos veces la misma sesión para
 *    un mismo participante (defensa adicional al UNIQUE de BD).
 *  - Solape (algoritmo del plan §6): agrupar por `fecha` y detectar
 *    pares con `a.start < b.end && a.end > b.start`.
 *  - Capacidad: `lockForUpdate` sobre cada sesión + count de selecciones
 *    existentes (excluyendo las de esta inscripción en update).
 *  - Requeridos: todos los talleres REQUIRED activos del evento deben
 *    tener al menos una selección en ESTA inscripción (no por
 *    participante — la selección es por participante, pero el control
 *    se hace por participante; si un participante no cubre un REQUIRED,
 *    falla).
 *
 * Concurrencia: los `lockForUpdate` viven dentro del `DB::transaction`
 * del caller (`CrearInscripcionAction` / `ActualizarInscripcionAction`),
 * por eso este helper NO abre transacción propia.
 */
class ValidarSeleccionesTallerAction
{
    /**
     * Validar todas las selecciones de todos los participantes de una
     * inscripción. Lanza `DomainException` ante el primer error.
     */
    public static function run(RegistrationDTO $dto): void
    {
        foreach ($dto->participants as $p) {
            self::runPorParticipante($dto, $p);
        }
    }

    /**
     * Validar las selecciones de UN participante: pertenencia, duplicado
     * y solape. La capacidad se valida en `runCapacidad()` y se separa
     * porque requiere lock transaccional — se invoca desde el mismo
     * `DB::transaction` del caller.
     */
    public static function runPorParticipante(RegistrationDTO $dto, ParticipantDTO $p): void
    {
        if (empty($p->talleres)) {
            return;
        }

        $evento = Evento::find($dto->eventId);
        if (! $evento) {
            return; // chequeo previo del caller
        }

        $sesiones = [];
        foreach ($p->talleres as $t) {
            $sesion = SesionCongreso::with('taller')
                ->where('id', $t->sesionCongresoId)
                ->first();

            if (! $sesion) {
                throw new \DomainException(
                    "La sesión de taller {$t->sesionCongresoId} no existe."
                );
            }

            if ($sesion->evento_id !== $evento->id) {
                throw new \DomainException(
                    "La sesión '{$sesion->titulo}' no pertenece a este evento."
                );
            }

            if (! $sesion->taller_id) {
                throw new \DomainException(
                    "La sesión '{$sesion->titulo}' no es seleccionable (no pertenece a un taller)."
                );
            }

            $taller = $sesion->taller;
            if (! $taller || $taller->evento_id !== $evento->id) {
                throw new \DomainException(
                    "El taller asociado a '{$sesion->titulo}' no pertenece a este evento."
                );
            }

            if (! $sesion->activa) {
                throw new \DomainException(
                    "La sesión '{$sesion->titulo}' no está activa."
                );
            }

            if (! $taller->activo) {
                throw new \DomainException(
                    "El taller '{$taller->nombre}' no está activo."
                );
            }

            $sesiones[] = [
                'sesion'  => $sesion,
                'taller'  => $taller,
                'dto'     => $t,
            ];
        }

        // Duplicado (defensa adicional al UNIQUE de BD).
        $ids = array_map(fn ($s) => $s['sesion']->id, $sesiones);
        if (count($ids) !== count(array_unique($ids))) {
            throw new \DomainException(
                'No podés seleccionar la misma sesión de taller más de una vez.'
            );
        }

        // Solape: agrupar por fecha, comparar pares por intersección.
        $porFecha = [];
        foreach ($sesiones as $s) {
            $key = $s['sesion']->fecha->format('Y-m-d');
            $porFecha[$key][] = $s;
        }

        foreach ($porFecha as $lista) {
            $n = count($lista);
            for ($i = 0; $i < $n; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    $a = $lista[$i]['sesion'];
                    $b = $lista[$j]['sesion'];

                    if (self::seSolapan($a, $b)) {
                        throw new \DomainException(
                            "Conflicto de horario entre '{$lista[$i]['taller']->nombre}' (" . substr($a->hora_inicio, 0, 5) . "–" . substr($a->hora_fin, 0, 5) . ") y '{$lista[$j]['taller']->nombre}' (" . substr($b->hora_inicio, 0, 5) . "–" . substr($b->hora_fin, 0, 5) . ")."
                        );
                    }
                }
            }
        }
    }

    /**
     * Validar capacidad por sesión, con `lockForUpdate` para evitar que
     * dos requests simultáneos vendan el último cupo. DEBE ejecutarse
     * dentro del mismo `DB::transaction` del caller. `$excludeInscripcionId`
     * permite excluir selecciones de la propia inscripción (update path).
     */
    public static function runCapacidad(
        RegistrationDTO $dto,
        ?int $excludeInscripcionId = null,
    ): void {
        // Mapa sesion_id => [taller, dto] (del primer participante que la trae).
        $seleccionesPorSesion = [];
        foreach ($dto->participants as $p) {
            foreach ($p->talleres as $t) {
                $seleccionesPorSesion[$t->sesionCongresoId] = true;
            }
        }

        if (empty($seleccionesPorSesion)) {
            return;
        }

        foreach (array_keys($seleccionesPorSesion) as $sesionId) {
            /** @var SesionCongreso $sesion */
            $sesion = SesionCongreso::where('id', $sesionId)
                ->lockForUpdate()
                ->first();

            if (! $sesion || $sesion->cupo === null) {
                continue; // sin cupo = sin límite
            }

            $query = ParticipanteTallerSesion::where('sesion_congreso_id', $sesion->id);
            if ($excludeInscripcionId !== null) {
                $query->whereHas('participante', function ($q) use ($excludeInscripcionId) {
                    $q->where('registration_id', $excludeInscripcionId);
                });
            }

            $ocupados = $query->count();

            if ($ocupados >= $sesion->cupo) {
                throw new \DomainException(
                    "La sesión '{$sesion->titulo}' ya no tiene cupos disponibles."
                );
            }
        }
    }

    /**
     * Validar que cada taller REQUIRED activo del evento tenga al menos
     * una selección por cada participante. Ver §1.5 del plan.
     */
    public static function runRequeridos(RegistrationDTO $dto): void
    {
        $evento = Evento::find($dto->eventId);
        if (! $evento) {
            return;
        }

        $requeridos = Taller::where('evento_id', $evento->id)
            ->where('activo', true)
            ->where('modalidad', 'REQUIRED')
            ->get();

        if ($requeridos->isEmpty()) {
            return;
        }

        foreach ($dto->participants as $p) {
            $talleresDelParticipante = [];
            foreach ($p->talleres as $t) {
                $sesion = SesionCongreso::find($t->sesionCongresoId);
                if ($sesion) {
                    $talleresDelParticipante[$sesion->taller_id] = true;
                }
            }

            foreach ($requeridos as $req) {
                if (! isset($talleresDelParticipante[$req->id])) {
                    throw new \DomainException(
                        "Falta seleccionar al menos un horario del taller obligatorio '{$req->nombre}'."
                    );
                }
            }
        }
    }

    private static function seSolapan(SesionCongreso $a, SesionCongreso $b): bool
    {
        $aStart = CarbonImmutable::parse($a->fecha->format('Y-m-d').' '.$a->hora_inicio);
        $aEnd   = CarbonImmutable::parse($a->fecha->format('Y-m-d').' '.$a->hora_fin);
        $bStart = CarbonImmutable::parse($b->fecha->format('Y-m-d').' '.$b->hora_inicio);
        $bEnd   = CarbonImmutable::parse($b->fecha->format('Y-m-d').' '.$b->hora_fin);

        return $aStart->lt($bEnd) && $aEnd->gt($bStart);
    }

    }
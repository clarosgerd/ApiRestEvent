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
     *
     * $sesionIdsPreviasPorIndice — bug real 02/09/2026 (reportado en UAT:
     * SIP cobró un pago adicional real y la aplicación falló igual, dejando
     * el intento en 'error' sin forma de recuperarse). Ver
     * ActualizarInscripcionPagadaAction: una vez pagado, un taller NUNCA se
     * puede quitar ("No se pueden quitar talleres que ya fueron pagados"),
     * así que revalidar su disponibilidad actual (activo/permite_inscripcion)
     * en cada edición posterior es una contradicción — si el organizador
     * deshabilita ese taller después (cupo lleno, lo que sea), el
     * participante queda bloqueado para siempre para editar CUALQUIER cosa
     * de su inscripción ya pagada, sin ninguna forma de "sacarlo" porque no
     * se puede quitar. Mapa índice-de-participante => ids de
     * sesion_congreso ya seleccionadas ANTES de esta edición (armado por el
     * caller); esas sesiones se eximen de los chequeos de disponibilidad
     * (activa/activo/permite_inscripcion) pero siguen validando pertenencia/
     * duplicado/solape igual que cualquier otra. Vacío por default —
     * CrearInscripcionAction (alta nueva, todo es "nuevo") y
     * ActualizarInscripcionAction (inscripción pendiente, todavía se puede
     * quitar cualquier taller) no lo necesitan y no lo pasan.
     */
    public static function run(RegistrationDTO $dto, array $sesionIdsPreviasPorIndice = []): void
    {
        foreach ($dto->participants as $i => $p) {
            self::runPorParticipante($dto, $p, $sesionIdsPreviasPorIndice[$i] ?? []);
        }
    }

    /**
     * Validar las selecciones de UN participante: pertenencia, duplicado
     * y solape. La capacidad se valida en `runCapacidad()` y se separa
     * porque requiere lock transaccional — se invoca desde el mismo
     * `DB::transaction` del caller.
     *
     * $sesionIdsPrevias — ver doc de `run()`.
     */
    public static function runPorParticipante(RegistrationDTO $dto, ParticipantDTO $p, array $sesionIdsPrevias = []): void
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

            // Grandfather clause para selecciones ya existentes (02/09/2026)
            // — ver doc de `run()`. Los 3 chequeos de disponibilidad de
            // abajo (sesión activa / taller activo / permite_inscripcion)
            // solo aplican a una selección REALMENTE nueva en esta edición;
            // una que el participante ya tenía de antes no se vuelve a
            // filtrar por esto, porque de todos modos no se le puede quitar.
            $esSeleccionPrevia = in_array((int) $t->sesionCongresoId, $sesionIdsPrevias, true);

            if (! $sesion->activa && ! $esSeleccionPrevia) {
                throw new \DomainException(
                    "La sesión '{$sesion->titulo}' no está activa."
                );
            }

            if (! $taller->activo && ! $esSeleccionPrevia) {
                throw new \DomainException(
                    "El taller '{$taller->nombre}' no está activo."
                );
            }

            // Deshabilitar un taller sin ocultarlo (28/08/2026) — ver
            // PLAN-TALLER-PERMITE-INSCRIPCION-28082026.md. Distinto de
            // `activo`: el taller sigue visible en elascenso/event, pero
            // no se puede seleccionar. `activo=false` ya lo bloquea arriba
            // (y además lo oculta) — este chequeo cubre el caso nuevo
            // (`activo=true`, `permite_inscripcion=false`).
            if (! $taller->permite_inscripcion && ! $esSeleccionPrevia) {
                throw new \DomainException(
                    "El taller '{$taller->nombre}' no está disponible para inscripción en este momento."
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
        array $sesionIdsPreviasPorIndice = [],
    ): void {
        // Mismo motivo que en run()/runPorParticipante(): una sesión que el
        // participante ya tenía de antes de esta edición no se puede quitar,
        // así que no tiene sentido volver a exigirle cupo libre — si otra
        // gente llenó el cupo mientras tanto, eso no es "culpa" de esta
        // edición ni algo que el participante pueda resolver.
        $sesionIdsPrevias = [];
        foreach ($sesionIdsPreviasPorIndice as $ids) {
            foreach ($ids as $id) {
                $sesionIdsPrevias[(int) $id] = true;
            }
        }

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
            if (isset($sesionIdsPrevias[(int) $sesionId])) {
                continue;
            }

            /** @var SesionCongreso $sesion */
            $sesion = SesionCongreso::where('id', $sesionId)
                ->lockForUpdate()
                ->first();

            if (! $sesion || $sesion->cupo === null) {
                continue; // sin cupo = sin límite
            }

            // Bug real preexistente encontrado el 26/08/2026 (al escribir
            // tests para el cobro SIP del monto adicional) — esto era
            // `whereHas(...)`, que en vez de EXCLUIR las filas propias de
            // $excludeInscripcionId del conteo, lo restringía a CONTAR
            // SOLO esas filas. En la práctica esto significaba que editar
            // una inscripción para agregar un taller nunca veía la
            // ocupación de ninguna OTRA inscripción — el chequeo de cupo
            // cruzado quedaba completamente roto en cualquier edición
            // (ActualizarInscripcionAction/ActualizarInscripcionPagadaAction),
            // solo funcionaba de verdad en el alta nueva (que llama esto
            // sin $excludeInscripcionId). Ningún test existente lo cubría
            // porque todos pasaban null acá.
            $query = ParticipanteTallerSesion::where('sesion_congreso_id', $sesion->id);
            if ($excludeInscripcionId !== null) {
                $query->whereDoesntHave('participante', function ($q) use ($excludeInscripcionId) {
                    $q->where('registration_id', $excludeInscripcionId);
                });
            }

            // Cupo secuestrado por inscripciones canceladas (31/08/2026,
            // bug real reportado por el usuario: "los cupos muestran
            // distinta información" entre elascenso/event y admin-eventos)
            // — antes esto contaba CUALQUIER fila, incluidas inscripciones
            // `pending` que expiraron y se cancelaron solas
            // (ExpirarInscripcionesPendientesAction nunca borra estas
            // filas, solo cambia `registrations.pago_status`), así que un
            // pago QR nunca completado dejaba el cupo de la sesión
            // ocupado para siempre. Mismo criterio que ya usa
            // FormType::inscritosVigentes() para el cupo general del
            // evento — `pending` sí reserva lugar (alguien pagando en
            // este momento), `cancelled`/`failed` no.
            $query->whereHas('participante.registration', function ($q) {
                $q->whereNotIn('pago_status', ['cancelled', 'failed']);
            });

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

        // permite_inscripcion=false (28/08/2026) excluido acá también —
        // no tiene sentido exigir la selección de un taller que nadie
        // puede seleccionar. Ver PLAN-TALLER-PERMITE-INSCRIPCION-28082026.md.
        $requeridos = Taller::where('evento_id', $evento->id)
            ->where('activo', true)
            ->where('permite_inscripcion', true)
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
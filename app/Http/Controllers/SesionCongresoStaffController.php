<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEventoScope;
use App\Models\Evento;
use App\Models\Answer;
use App\Models\FormularioCampos;
use App\Models\Participante;
use App\Services\FormTypeService;
use App\Models\SesionCongreso;
use App\Services\AdminAuditLogger;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Vinculación de participantes a sesiones de congreso, en 2 roles
 * posibles: **staff** (form_type con `es_staff=true`, ver
 * brain/PLAN-ASIGNACION-STAFF-SESIONES-CONGRESO-13082026.md) y
 * **ponente** (form_type con `es_ponente=true`, ver
 * brain/PLAN-VINCULACION-PONENTES-SESIONES-CONGRESO-13082026.md, agregado
 * el mismo día reusando este mismo controller/tabla). Decisión del
 * organizador, hecha después de que la gente ya se inscribió (no
 * self-select en el formulario público).
 *
 * Distinto de AsistenciaSesionController: eso es check-in de quién
 * ASISTIÓ a una sesión; esto es a quién se VINCULÓ (staff que apoya, o
 * ponente que expone) — no requiere pago confirmado ni check-in.
 *
 * `rol` viaja como querystring en GET/DELETE y como campo del body en
 * POST — default `'staff'` en todos lados para no romper a quien todavía
 * no manda el parámetro (compatibilidad con el primer alcance del
 * 13/08/2026, que era solo staff).
 */
class SesionCongresoStaffController extends Controller
{
    use AuthorizesEventoScope;

    private const ROLES = ['staff', 'ponente'];

    /**
     * Participantes del evento con el flag de form_type correspondiente al
     * rol pedido (`es_staff` o `es_ponente`) — para poblar el selector de
     * "+ Asignar ayudante"/"+ Vincular ponente" en admin-eventos.
     */
    public function disponibles(Request $request, Evento $event): JsonResponse
    {
        $this->assertCanWriteEvento($event->id);
        $rol = $this->validarRol($request->query('rol', 'staff'));
        $columna = $this->columnaFlagPara($rol);

        $disponibles = Participante::whereHas('registration', function ($q) use ($event, $columna) {
            $q->where('evento_id', $event->id)
                ->whereNotIn('pago_status', ['cancelled', 'failed'])
                ->whereHas('formType', fn ($ft) => $ft->where($columna, true));
        })->get(['id', 'nombre', 'apellido', 'correo']);

        // Ponente (26/09/2026): lo que dijo que va a dictar en el formulario (preguntas
        // `taller_dictara` y `tema_charla`), para que el organizador lo tenga a la vista
        // al vincularlo — la vinculación a la sesión sigue siendo manual.
        if ($rol === 'ponente' && $disponibles->isNotEmpty()) {
            $preguntas = FormularioCampos::whereIn('nombre_campo', [FormTypeService::CAMPO_TALLER_DICTARA, FormTypeService::CAMPO_TEMA_CHARLA])
                ->pluck('nombre_campo', 'id');
            $respuestas = Answer::whereIn('participante_id', $disponibles->pluck('id'))
                ->whereIn('question_id', $preguntas->keys())
                ->get(['participante_id', 'question_id', 'value'])
                ->groupBy('participante_id');

            $disponibles->each(function ($p) use ($respuestas, $preguntas) {
                $p->taller_dictara = null;
                $p->tema_charla = null;
                foreach ($respuestas->get($p->id, []) as $r) {
                    $campo = $preguntas[$r->question_id] ?? null;
                    if ($campo !== null && trim((string) $r->value) !== '') {
                        $p->{$campo} = trim((string) $r->value);
                    }
                }
            });
        }

        return response()->json([
            'success' => true,
            'data' => $disponibles,
        ]);
    }

    public function index(Request $request, Evento $event, SesionCongreso $sesion): JsonResponse
    {
        $this->assertCanWriteEvento($event->id);
        $this->assertSesionPerteneceAEvento($event, $sesion);
        $rol = $this->validarRol($request->query('rol', 'staff'));

        return response()->json([
            'success' => true,
            'data' => $this->relacionPara($sesion, $rol)->get(['participantes.id', 'nombre', 'apellido', 'correo']),
        ]);
    }

    public function store(Request $request, Evento $event, SesionCongreso $sesion): JsonResponse
    {
        $this->assertCanWriteEvento($event->id);
        $this->assertSesionPerteneceAEvento($event, $sesion);

        $data = $request->validate([
            'participante_id' => 'required|integer',
            'rol' => 'sometimes|nullable|string|in:staff,ponente',
        ]);
        $rol = $data['rol'] ?? 'staff';
        $columna = $this->columnaFlagPara($rol);

        $participante = Participante::with('registration.formType')->find($data['participante_id']);

        if (!$participante || !$participante->registration || (int) $participante->registration->evento_id !== $event->id) {
            return response()->json(['success' => false, 'error' => 'Este participante no pertenece a este evento.'], 404);
        }

        if (!optional($participante->registration->formType)->{$columna}) {
            $etiqueta = $rol === 'ponente' ? 'Ponente' : 'Staff';
            return response()->json([
                'success' => false,
                'error' => "Este participante no está inscrito bajo un tipo de formulario de {$etiqueta}.",
            ], 422);
        }

        $relacion = $this->relacionPara($sesion, $rol);
        if ($relacion->where('participante_id', $participante->id)->exists()) {
            return response()->json([
                'success' => true,
                'alreadyAssigned' => true,
            ]);
        }

        $relacion->attach($participante->id, [
            'rol' => $rol,
            'asignado_por_admin_user_id' => auth('admins')->id(),
        ]);

        AdminAuditLogger::log("vincular_{$rol}_sesion", 'SesionCongreso', $sesion->id, $event->id, null, [
            'participante_id' => $participante->id,
            'rol' => $rol,
        ]);

        return response()->json([
            'success' => true,
            'alreadyAssigned' => false,
        ], 201);
    }

    public function destroy(Request $request, Evento $event, SesionCongreso $sesion, Participante $participante): JsonResponse
    {
        $this->assertCanWriteEvento($event->id);
        $this->assertSesionPerteneceAEvento($event, $sesion);
        // input() en vez de query(): un DELETE puede llegar con `rol` en
        // el body JSON (así lo manda Laravel's Http::delete() desde
        // admin-eventos) o en la querystring (así se probó a mano con
        // curl) — input() cubre ambos casos.
        $rol = $this->validarRol($request->input('rol', 'staff'));

        $this->relacionPara($sesion, $rol)->detach($participante->id);

        AdminAuditLogger::log("desvincular_{$rol}_sesion", 'SesionCongreso', $sesion->id, $event->id, [
            'participante_id' => $participante->id,
            'rol' => $rol,
        ], null);

        return response()->json(['success' => true]);
    }

    private function relacionPara(SesionCongreso $sesion, string $rol): BelongsToMany
    {
        return $rol === 'ponente' ? $sesion->ponentesVinculados() : $sesion->staffAsignado();
    }

    private function columnaFlagPara(string $rol): string
    {
        return $rol === 'ponente' ? 'es_ponente' : 'es_staff';
    }

    private function validarRol(?string $rol): string
    {
        $rol = $rol ?: 'staff';
        abort_unless(in_array($rol, self::ROLES, true), 422, 'Rol inválido, debe ser "staff" o "ponente".');

        return $rol;
    }

    private function assertSesionPerteneceAEvento(Evento $event, SesionCongreso $sesion): void
    {
        abort_if($sesion->evento_id !== $event->id, 404);
    }
}

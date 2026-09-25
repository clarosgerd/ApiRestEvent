<?php

namespace App\Http\Controllers;

use App\Models\Answer;
use App\Models\Category;
use App\Models\EmpresaExpositora;
use App\Models\FormularioCampos;
use App\Models\LeadCapturado;
use App\Models\Participante;
use App\Models\Registration;
use App\Support\LeadsCapturadosData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * SmartStand (25/09/2026) — captura de leads por la empresa expositora
 * (guard `expositores`, siempre scoped al evento de la propia cuenta).
 *
 * Solo se puede escanear/capturar a participantes de inscripciones PAGADAS
 * del MISMO evento de la empresa. Una referencia de otro evento (o sin pago
 * confirmado) se trata como inexistente, igual criterio que
 * RegistrationController::checkinLookup(): no filtrar datos entre eventos.
 */
class EmpresaExpositoraLeadController extends Controller
{
    /** Lookup por la referencia que codifica el QR del gafete. */
    public function buscar(Request $request, string $referencia): JsonResponse
    {
        $cuenta = $this->cuenta($request);

        $registration = Registration::where('referencia', strtoupper(trim($referencia)))
            ->where('evento_id', $cuenta->evento_id)
            ->where('pago_status', 'paid')
            ->with('participants')
            ->first();

        if (! $registration) {
            return response()->json([
                'success' => false,
                'error'   => 'No se encontró una inscripción pagada con esa referencia en este evento.',
            ], 404);
        }

        $categorias = $this->categoriasDelEvento($cuenta);
        $leads = LeadCapturado::where('empresa_expositora_id', $cuenta->id)
            ->whereIn('participante_id', $registration->participants->pluck('id'))
            ->get()
            ->keyBy('participante_id');
        $respuestas = $this->respuestasPorParticipante($registration->participants);

        return response()->json([
            'success'      => true,
            'referencia'   => $registration->referencia,
            'participantes' => $registration->participants->map(fn (Participante $p) => [
                'id'         => $p->id,
                'nombre'     => $p->nombre,
                'apellido'   => $p->apellido,
                'correo'     => $p->correo,
                'telefono'   => $p->telefono,
                'ciudad'     => $p->ciudad,
                'categoria'  => $categorias->get((string) $p->categoria) ?? $p->categoria,
                'respuestas' => $respuestas->get($p->id, []),
                'lead'       => ($lead = $leads->get($p->id))
                    ? ['nota' => $lead->nota, 'calificacion' => $lead->calificacion]
                    : null,
            ])->values(),
        ]);
    }

    /** Captura (o actualiza) el lead de un participante escaneado. */
    public function store(Request $request): JsonResponse
    {
        $cuenta = $this->cuenta($request);

        $data = $request->validate([
            'participante_id' => ['required', 'integer'],
            'nota'            => ['nullable', 'string', 'max:2000'],
            'calificacion'    => ['nullable', 'integer', 'between:1,5'],
        ]);

        $participante = Participante::where('id', $data['participante_id'])
            ->whereHas('registration', fn ($q) => $q
                ->where('evento_id', $cuenta->evento_id)
                ->where('pago_status', 'paid'))
            ->first();

        if (! $participante) {
            return response()->json([
                'success' => false,
                'error'   => 'El participante no pertenece a una inscripción pagada de este evento.',
            ], 404);
        }

        $lead = LeadCapturado::firstOrNew([
            'empresa_expositora_id' => $cuenta->id,
            'participante_id'       => $participante->id,
        ]);
        $creado = ! $lead->exists;

        // Re-escanear sin mandar nota/calificación NO las borra; y la fecha
        // de captura es la del primer escaneo (las estadísticas por día/hora
        // miden cuándo llegó el visitante, no cuándo se editó la nota).
        if ($request->has('nota')) {
            $lead->nota = $data['nota'];
        }
        if ($request->has('calificacion')) {
            $lead->calificacion = $data['calificacion'];
        }
        if ($creado) {
            $lead->capturado_at = now();
        }
        $lead->save();

        return response()->json([
            'success' => true,
            'creado'  => $creado,
            'lead'    => $this->presentarLead($lead->load('participante'), $this->categoriasDelEvento($cuenta)),
        ], $creado ? 201 : 200);
    }

    /** Leads propios, paginados (más recientes primero). */
    public function index(Request $request): JsonResponse
    {
        $cuenta = $this->cuenta($request);
        $porPagina = min(max((int) $request->query('per_page', 25), 1), 100);

        $paginador = LeadCapturado::where('empresa_expositora_id', $cuenta->id)
            ->with('participante')
            ->orderByDesc('capturado_at')
            ->orderByDesc('id')
            ->paginate($porPagina);

        $categorias = $this->categoriasDelEvento($cuenta);

        return response()->json([
            'success' => true,
            'data'    => $paginador->getCollection()->map(fn ($l) => $this->presentarLead($l, $categorias))->values(),
            'meta'    => [
                'page'     => $paginador->currentPage(),
                'perPage'  => $paginador->perPage(),
                'total'    => $paginador->total(),
                'lastPage' => $paginador->lastPage(),
            ],
        ]);
    }

    public function dashboard(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => LeadsCapturadosData::paraEmpresa($this->cuenta($request)),
        ]);
    }

    /** CSV de los leads propios (streaming). */
    public function exportCsv(Request $request): StreamedResponse
    {
        $cuenta = $this->cuenta($request);
        $categorias = $this->categoriasDelEvento($cuenta);

        $leads = LeadCapturado::where('empresa_expositora_id', $cuenta->id)
            ->with('participante')
            ->orderBy('capturado_at')
            ->get();

        return response()->streamDownload(function () use ($leads, $categorias) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Nombre', 'Apellido', 'Correo', 'Teléfono', 'Ciudad', 'Categoría', 'Calificación', 'Nota', 'Capturado']);
            foreach ($leads as $lead) {
                $p = $lead->participante;
                fputcsv($out, array_map([self::class, 'celdaSegura'], [
                    $p->nombre,
                    $p->apellido,
                    $p->correo,
                    $p->telefono,
                    $p->ciudad,
                    $categorias->get((string) $p->categoria) ?? $p->categoria,
                    $lead->calificacion,
                    $lead->nota,
                    optional($lead->capturado_at)->format('Y-m-d H:i'),
                ]));
            }
            fclose($out);
        }, 'leads-' . $cuenta->id . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Neutraliza fórmulas de Excel/Sheets (=, +, -, @) en celdas cuyo texto
     * viene de datos ingresados por terceros (nombre, ciudad, nota...): el
     * expositor abre este CSV en una planilla.
     */
    public static function celdaSegura(mixed $valor): mixed
    {
        if (is_string($valor) && $valor !== '' && str_contains("=+-@\t\r", $valor[0])) {
            return "'" . $valor;
        }

        return $valor;
    }

    private function cuenta(Request $request): EmpresaExpositora
    {
        return $request->user('expositores');
    }

    /** id (string) => nombre de las categorías del evento de la cuenta. */
    private function categoriasDelEvento(EmpresaExpositora $cuenta): Collection
    {
        return Category::where('event_id', $cuenta->evento_id)
            ->pluck('name', 'id')
            ->mapWithKeys(fn ($nombre, $id) => [(string) $id => $nombre]);
    }

    private function presentarLead(LeadCapturado $lead, Collection $categorias): array
    {
        $p = $lead->participante;

        return [
            'id'           => $lead->id,
            'capturadoAt'  => optional($lead->capturado_at)->toIso8601String(),
            'nota'         => $lead->nota,
            'calificacion' => $lead->calificacion,
            'participante' => [
                'id'        => $p->id,
                'nombre'    => $p->nombre,
                'apellido'  => $p->apellido,
                'correo'    => $p->correo,
                'telefono'  => $p->telefono,
                'ciudad'    => $p->ciudad,
                'categoria' => $categorias->get((string) $p->categoria) ?? $p->categoria,
            ],
        ];
    }

    /**
     * Respuestas a las preguntas custom del formulario (ej. una eventual
     * "Especialidad"), tal cual las cargó el participante — sin parsear ni
     * normalizar: hoy no existe un campo estructurado de especialidad.
     *
     * @return Collection<int, array<int, array{pregunta: string, respuesta: ?string}>>
     */
    private function respuestasPorParticipante(Collection $participantes): Collection
    {
        $answers = Answer::whereIn('participante_id', $participantes->pluck('id'))->get();
        if ($answers->isEmpty()) {
            return collect();
        }

        $etiquetas = FormularioCampos::whereIn('id', $answers->pluck('question_id')->unique())
            ->pluck('etiqueta', 'id');

        return $answers->groupBy('participante_id')->map(
            fn (Collection $grupo) => $grupo->map(fn (Answer $a) => [
                'pregunta'  => $etiquetas->get($a->question_id, 'Pregunta #' . $a->question_id),
                'respuesta' => $a->value,
            ])->values()->all()
        );
    }
}

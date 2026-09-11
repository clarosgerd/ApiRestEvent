<?php

namespace App\Support;

use App\Models\Evento;
use App\Models\FormType;
use App\Models\Participante;
use App\Models\Registration;

/**
 * Reporte de trazabilidad de inscripciones (admin general, cross-evento,
 * 10/09/2026) — ver brain/api_rest_event/PLAN-REPORTE-TRAZABILIDAD-10092026.md.
 * Pedido del usuario: un reporte para super_admin que cruce, para TODOS los
 * eventos a la vez (no uno solo como el resto de los reportes existentes —
 * ver ReporteInscritosData/BalanceEventoData), tipo de evento, si la
 * inscripción está pagada, tipo de pago, poleras/tallas, talleres, y la
 * trazabilidad completa de las adiciones (pagos_adicionales_inscripcion)
 * hechas sobre una inscripción ya pagada.
 *
 * A diferencia de ReporteInscritosData/BalanceEventoData (por-un-evento,
 * cargan todo en memoria y agrupan con arrays PHP), acá el universo es
 * potencialmente todos los eventos — el query es paginado desde
 * `Registration` directo, con filtros aplicados en SQL antes de traer nada
 * a memoria.
 *
 * La fila es UNA Registration (no un Participante ni una transacción
 * separada por adición): las adiciones son por `registration_id`, no por
 * participante — centrar la fila en la inscripción evita dividir
 * arbitrariamente un monto que en un alta grupal no tiene dueño individual
 * claro. El detalle por participante (categoría, talla de polera, talleres)
 * y por adición (referencia, monto, estado, fechas) viaja embebido en cada
 * fila para que el panel pueda mostrar un detalle expandible sin una
 * llamada aparte por fila.
 */
class ReporteTrazabilidadData
{
    private const PER_PAGE_DEFAULT = 25;

    private const PER_PAGE_MAX = 100;

    public static function paginar(array $filtros): array
    {
        $query = Registration::query()
            ->with([
                'totals',
                // Evento usa la columna 'nombre' (español), a diferencia de
                // Category/FormType que usan 'name' (inglés) — inconsistencia
                // real del esquema, no un typo.
                'evento:id,nombre',
                'evento.categories:id,event_id,name',
                'formType:id,name,tipo',
                'participants.souvenirParticipante',
                'participants.talleresSesiones.taller',
                'participants.talleresSesiones.sesionCongreso',
                'pagosAdicionales',
            ]);

        if (!empty($filtros['evento_id'])) {
            $query->where('evento_id', (int) $filtros['evento_id']);
        }

        // "Tipo de evento" (decisión del usuario) = form_types.tipo, no el
        // catálogo tipos_evento — es lo que realmente varía por
        // inscripción dentro de un mismo evento.
        if (!empty($filtros['tipo_evento'])) {
            $query->whereHas('formType', fn ($q) => $q->where('tipo', $filtros['tipo_evento']));
        }

        if (!empty($filtros['pago_status'])) {
            $query->where('pago_status', $filtros['pago_status']);
        }

        if (!empty($filtros['tipo_pago'])) {
            $query->where('tipo_pago', $filtros['tipo_pago']);
        }

        if (!empty($filtros['tiene_adiciones'])) {
            $query->whereHas('pagosAdicionales');
        }

        // 'fecha' está indexada, 'created_at' no (gotcha confirmado contra
        // el esquema real) — usar siempre 'fecha' para rango/orden acá.
        if (!empty($filtros['fecha_desde'])) {
            $query->whereDate('fecha', '>=', $filtros['fecha_desde']);
        }
        if (!empty($filtros['fecha_hasta'])) {
            $query->whereDate('fecha', '<=', $filtros['fecha_hasta']);
        }

        if (!empty($filtros['search'])) {
            $termino = '%'.$filtros['search'].'%';
            $query->where(function ($q) use ($termino) {
                $q->where('referencia', 'like', $termino)
                    ->orWhereHas('participants', function ($pq) use ($termino) {
                        $pq->where('nombre', 'like', $termino)
                            ->orWhere('apellido', 'like', $termino)
                            ->orWhere('numero_documento', 'like', $termino)
                            ->orWhere('correo', 'like', $termino);
                    });
            });
        }

        $perPage = min((int) ($filtros['per_page'] ?? self::PER_PAGE_DEFAULT), self::PER_PAGE_MAX);
        $page = max((int) ($filtros['page'] ?? 1), 1);

        $paginator = $query->orderByDesc('fecha')->paginate($perPage, ['*'], 'page', $page);

        // souvenirIdsPolera se resuelve UNA vez para los form_types
        // presentes en ESTA página (no globalmente) — mismo patrón que
        // ReporteInscritosData::agruparPoleras(), adaptado a paginado, para
        // no calcular esto contra los 58 eventos cuando la página trae 25.
        $formTypeIds = $paginator->getCollection()
            ->pluck('form_types_id')
            ->filter()
            ->unique()
            ->values()
            ->all();
        $souvenirIdsPolera = TallaPoleraData::souvenirIdsPolera($formTypeIds);

        return [
            'data' => $paginator->getCollection()
                ->map(fn (Registration $r) => self::mapRow($r, $souvenirIdsPolera))
                ->all(),
            'meta' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'perPage' => $paginator->perPage(),
            ],
            'filtrosDisponibles' => self::filtrosDisponibles(),
        ];
    }

    private static function mapRow(Registration $r, array $souvenirIdsPolera): array
    {
        $categoriasPorId = $r->evento?->categories?->pluck('name', 'id') ?? collect();

        $participantes = $r->participants->map(function (Participante $p) use ($categoriasPorId, $souvenirIdsPolera) {
            // TallaPoleraData::resolver() ya maneja el fallback correcto:
            // souvenir es_polera=true si el evento lo modela así, si no el
            // campo legacy participantes.polera (real para eventos que
            // todavía usan form_types.hasshirt, ver docblock de la clase).
            $talla = TallaPoleraData::resolver($p, $souvenirIdsPolera);
            $tienePolera = $talla !== null && $talla !== '' && $talla !== 'No shirt';

            return [
                'nombre' => $p->nombre,
                'apellido' => $p->apellido,
                'numeroDocumento' => $p->numero_documento,
                'categoria' => $categoriasPorId[$p->categoria] ?? $p->categoria,
                'tienePolera' => $tienePolera,
                'tallaPolera' => $tienePolera ? $talla : null,
                'talleres' => $p->talleresSesiones->map(fn ($pts) => [
                    'tallerNombre' => $pts->taller->nombre ?? 'Sin especificar',
                    'sesionTitulo' => $pts->sesionCongreso->titulo ?? null,
                    'monto' => round((float) $pts->total, 2),
                    'pagoPendiente' => (bool) $pts->pago_pendiente,
                ])->all(),
            ];
        });

        $adiciones = $r->pagosAdicionales->map(fn ($pa) => [
            'referencia' => $pa->referencia,
            'monto' => round((float) $pa->monto, 2),
            // Enum DISTINTO al de registrations.pago_status (pending|paid|
            // expired|error acá, vs pending|paid|failed|cancelled allá) —
            // se expone en su propio objeto a propósito, sin mezclarlo con
            // `pagoStatus` de arriba.
            'pagoStatus' => $pa->pago_status,
            'creadoEn' => optional($pa->created_at)->toIso8601String(),
            'pagadoEn' => optional($pa->paid_at)->toIso8601String(),
        ])->values();

        return [
            'referencia' => $r->referencia,
            'eventoId' => $r->evento_id,
            'eventoNombre' => $r->evento?->nombre ?? $r->evento_nombre,
            'formTypeNombre' => $r->formType?->name,
            'formTypeTipo' => $r->formType?->tipo,
            'pagoStatus' => $r->pago_status,
            'tipoPago' => $r->tipo_pago,
            'fecha' => optional($r->fecha)->toIso8601String(),
            'montoInscripcion' => round((float) ($r->totals?->grand_total ?? 0), 2),
            'cantidadParticipantes' => $participantes->count(),
            'tienePoleras' => $participantes->contains('tienePolera', true),
            'cantidadPoleras' => $participantes->where('tienePolera', true)->count(),
            'tieneTalleres' => $participantes->contains(fn ($p) => count($p['talleres']) > 0),
            'cantidadTalleres' => $participantes->sum(fn ($p) => count($p['talleres'])),
            'participantes' => $participantes->values()->all(),
            'adiciones' => $adiciones->all(),
            'montoAdicionesPagadas' => round($adiciones->where('pagoStatus', 'paid')->sum('monto'), 2),
            'cantidadAdicionesPendientes' => $adiciones->where('pagoStatus', 'pending')->count(),
        ];
    }

    /**
     * Opciones para los `<select>` de filtro del panel — solo los valores
     * REALMENTE usados (no los 22 del enum completo de form_types.tipo),
     * para que el panel no tenga que pedir esto por separado.
     */
    private static function filtrosDisponibles(): array
    {
        return [
            'eventos' => Evento::orderBy('nombre')->get(['id', 'nombre'])
                ->map(fn (Evento $e) => ['id' => $e->id, 'nombre' => $e->nombre])
                ->all(),
            'tiposEvento' => FormType::query()
                ->whereNotNull('tipo')
                ->distinct()
                ->orderBy('tipo')
                ->pluck('tipo')
                ->all(),
            'tiposPago' => Registration::query()
                ->whereNotNull('tipo_pago')
                ->where('tipo_pago', '!=', '')
                ->distinct()
                ->orderBy('tipo_pago')
                ->pluck('tipo_pago')
                ->all(),
        ];
    }
}

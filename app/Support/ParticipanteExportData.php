<?php

namespace App\Support;

use App\Models\Evento;
use App\Models\FormularioCampos;
use App\Models\Genero;
use App\Models\NumeracionRango;
use App\Models\Participante;
use Illuminate\Support\Collection;

/**
 * Mapeo de un Participante al shape usado por `ParticipanteController::porEvento()`
 * (02/10/2026) — extraído de ese método, sin tocar su lógica, para reusarlo
 * tal cual en `StaffAppController::participantes()` (descarga offline para
 * la app de staff) sin duplicar el array de campos.
 *
 * Los callers deben cargar `answers` y `souvenirParticipante` (eager) para no
 * hacer N+1 — ver porEvento() y StaffAppController::participantes().
 */
class ParticipanteExportData
{
    public const COLUMNS = [
        'id', 'registration_id', 'nombre', 'apellido', 'alias', 'numero_documento',
        'categoria', 'numero_corredor', 'chip', 'correo', 'telefono', 'direccion',
        'ciudad', 'genero', 'fecha_nacimiento', 'edad', 'polera', 'checked_in_at', 'subtotal',
        'promo_codigo', 'promo_descuento',
    ];

    private function __construct(
        private readonly Collection $categoriasPorId,
        private readonly array $souvenirIdsPolera,
        private readonly Evento $event,
        private readonly bool $hayRecategorizacion,
        private readonly Collection $numeracionRangosDelEvento,
        private readonly Collection $generosPorNombre,
        private readonly Collection $preguntasReporte,
    ) {
    }

    public static function paraEvento(Evento $event): self
    {
        $categoriasPorId = $event->categories->keyBy(fn ($c) => (string) $c->id);
        $formTypeIds = $event->formTypes()->pluck('id')->all();
        $souvenirIdsPolera = TallaPoleraData::souvenirIdsPolera($formTypeIds);

        $numeracionRangosDelEvento = NumeracionRango::whereHas(
            'category',
            fn ($q) => $q->where('event_id', $event->id)
        )->with('category')->get();
        $hayRecategorizacion = $numeracionRangosDelEvento->isNotEmpty();
        $generosPorNombre = $hayRecategorizacion ? Genero::all()->keyBy('nombre') : collect();

        // Preguntas propias del formulario marcadas "En reporte" (flag
        // visible_en_reporte, ya existente desde admin-eventos). Un mismo
        // nombre_campo puede existir en varios form_types: se agrupa y cada
        // participante toma la respuesta de cualquiera de sus ids.
        $preguntasReporte = FormularioCampos::whereIn('form_types_id', $formTypeIds)
            ->where('visible_en_reporte', true)
            ->orderBy('form_types_id')
            ->orderBy('orden')
            ->orderBy('id')
            ->get()
            ->groupBy('nombre_campo')
            ->map(fn (Collection $grupo, string $nombreCampo) => [
                'nombre_campo' => $nombreCampo,
                'etiqueta' => $grupo->first()->etiqueta ?: $nombreCampo,
                'ids' => $grupo->pluck('id')->all(),
            ])
            ->values();

        return new self(
            $categoriasPorId,
            $souvenirIdsPolera,
            $event,
            $hayRecategorizacion,
            $numeracionRangosDelEvento,
            $generosPorNombre,
            $preguntasReporte,
        );
    }

    public function mapear(Participante $p): array
    {
        $esUsdFijo = $p->registration->moneda_pago === 'USD';
        $importe = (float) $p->subtotal;
        $importeTaller = round((float) $p->talleresSesiones->sum('total'), 2);
        if ($esUsdFijo) {
            $categoria = $this->categoriasPorId->get((string) $p->categoria);
            $importe = (float) ($categoria->price_usd ?? 0);
            $importeTaller = round(
                (float) $p->talleresSesiones->sum(
                    fn ($ts) => (float) ($ts->sesionCongreso->price_usd ?? $ts->taller->price_usd ?? 0)
                ),
                2
            );
        }

        // Precio de la polera (souvenir marcado es_polera), para que el reporte
        // de carrera pueda separarlo del importe de inscripción.
        $importePolera = round((float) $p->souvenirParticipante
            ->whereIn('souvenir_id', $this->souvenirIdsPolera)
            ->sum('precio'), 2);

        $categoriaRecalculada = null;
        $categoriaRecalculadaColor = null;
        if ($this->hayRecategorizacion) {
            $recategorizacion = RecategorizacionResolver::paraParticipante(
                $p, $this->event, $this->numeracionRangosDelEvento, $this->generosPorNombre, $this->categoriasPorId
            );
            if ($recategorizacion) {
                $categoriaRecalculada = $recategorizacion['category']->name;
                $categoriaRecalculadaColor = $recategorizacion['color'];
            }
        }

        return [
            'id'              => $p->id,
            'referencia'      => $p->registration->referencia,
            'nombre'          => $p->nombre,
            'apellido'        => $p->apellido,
            'alias'           => $p->alias,
            'numeroDocumento' => $p->numero_documento,
            'categoria'       => $p->categoria,
            'numeroCorredor'  => $p->numero_corredor,
            'chip'            => $p->chip,
            'correo'          => $p->correo,
            'telefono'        => $p->telefono,
            'direccion'       => $p->direccion,
            'ciudad'          => $p->ciudad,
            'genero'          => $p->genero,
            'fechaNacimiento' => optional($p->fecha_nacimiento)->format('Y-m-d'),
            'polera'          => TallaPoleraData::resolver($p, $this->souvenirIdsPolera),
            'pagoStatus'      => $p->registration->pago_status,
            'tipoPago'        => $p->registration->tipo_pago,
            'checkedInAt'     => optional($p->checked_in_at)->toIso8601String(),
            'importe'         => $importe,
            'importePolera'   => $importePolera,
            'importeTaller'   => $importeTaller,
            'importeTotal'    => round($importe + $importeTaller, 2),
            // Descuento por código promocional — `importe` ya viene neto de
            // la promo; estos campos dejan ver qué código se usó y cuánto
            // se descontó (organizador, reportes de carrera y de congreso).
            'promoCodigo'     => $p->promo_codigo ?: null,
            'promoDescuento'  => round((float) $p->promo_descuento, 2),
            'fechaInscripcion' => optional($p->registration->fecha)->toIso8601String(),
            'categoriaRecalculada'      => $categoriaRecalculada,
            'categoriaRecalculadaColor' => $categoriaRecalculadaColor,
            'respuestas'      => $this->respuestasDe($p),
        ];
    }

    /**
     * Respuestas a las preguntas "En reporte" del evento, una entrada por
     * pregunta (en el mismo orden para todos los participantes). Valor ''
     * cuando el participante no respondió (o su formulario no tiene esa pregunta).
     *
     * @return list<array{nombre_campo: string, etiqueta: string, valor: string}>
     */
    private function respuestasDe(Participante $p): array
    {
        if ($this->preguntasReporte->isEmpty()) {
            return [];
        }

        $valorPorPregunta = $p->answers->keyBy('question_id');

        return $this->preguntasReporte->map(function (array $pregunta) use ($valorPorPregunta) {
            $respuesta = collect($pregunta['ids'])
                ->map(fn (int $id) => $valorPorPregunta->get($id))
                ->first(fn ($a) => $a !== null);

            return [
                'nombre_campo' => $pregunta['nombre_campo'],
                'etiqueta' => $pregunta['etiqueta'],
                'valor' => (string) ($respuesta?->value ?? ''),
            ];
        })->values()->all();
    }
}

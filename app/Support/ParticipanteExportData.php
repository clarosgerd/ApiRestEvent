<?php

namespace App\Support;

use App\Models\Evento;
use App\Models\Genero;
use App\Models\NumeracionRango;
use App\Models\Participante;
use Illuminate\Support\Collection;

/**
 * Mapeo de un Participante al shape usado por `ParticipanteController::porEvento()`
 * (02/10/2026) — extraído de ese método, sin tocar su lógica, para reusarlo
 * tal cual en `StaffAppController::participantes()` (descarga offline para
 * la app de staff) sin duplicar el array de campos.
 */
class ParticipanteExportData
{
    public const COLUMNS = [
        'id', 'registration_id', 'nombre', 'apellido', 'alias', 'numero_documento',
        'categoria', 'numero_corredor', 'chip', 'correo', 'telefono', 'direccion',
        'ciudad', 'genero', 'fecha_nacimiento', 'edad', 'polera', 'checked_in_at', 'subtotal',
    ];

    private function __construct(
        private readonly Collection $categoriasPorId,
        private readonly array $souvenirIdsPolera,
        private readonly Evento $event,
        private readonly bool $hayRecategorizacion,
        private readonly Collection $numeracionRangosDelEvento,
        private readonly Collection $generosPorNombre,
    ) {
    }

    public static function paraEvento(Evento $event): self
    {
        $categoriasPorId = $event->categories->keyBy(fn ($c) => (string) $c->id);
        $souvenirIdsPolera = TallaPoleraData::souvenirIdsPolera($event->formTypes()->pluck('id')->all());

        $numeracionRangosDelEvento = NumeracionRango::whereHas(
            'category',
            fn ($q) => $q->where('event_id', $event->id)
        )->with('category')->get();
        $hayRecategorizacion = $numeracionRangosDelEvento->isNotEmpty();
        $generosPorNombre = $hayRecategorizacion ? Genero::all()->keyBy('nombre') : collect();

        return new self(
            $categoriasPorId,
            $souvenirIdsPolera,
            $event,
            $hayRecategorizacion,
            $numeracionRangosDelEvento,
            $generosPorNombre,
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
            'importeTaller'   => $importeTaller,
            'importeTotal'    => round($importe + $importeTaller, 2),
            'fechaInscripcion' => optional($p->registration->fecha)->toIso8601String(),
            'categoriaRecalculada'      => $categoriaRecalculada,
            'categoriaRecalculadaColor' => $categoriaRecalculadaColor,
        ];
    }
}

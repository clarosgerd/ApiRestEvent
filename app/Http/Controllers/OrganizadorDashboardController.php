<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use App\Models\Participante;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

class OrganizadorDashboardController extends Controller
{
    /**
     * Los 4 estados reales de `registrations.pago_status` (migración
     * create_registrations_table) — se muestran todos por separado, sin
     * fusionar "failed" con "cancelled".
     */
    private const ESTADOS = ['paid', 'pending', 'cancelled', 'failed'];

    /**
     * Dashboard de solo lectura para el organizador — accedido vía link
     * firmado (sin login, ver routes/web.php), generado con
     * `php artisan organizador:generar-link {evento}`.
     */
    public function show(Evento $evento)
    {
        $participantes = $this->participantesDelEvento($evento)->get();

        return view('organizador.dashboard', [
            'evento'          => $evento,
            'totalGeneral'    => $this->contarPorEstado($participantes),
            'porCategoria'    => $this->agruparPor($participantes, fn ($p) => $p->categoria),
            // Se agrupa por form_types_id (no por nombre) para poder armar el
            // link de descarga filtrado directo con ese id.
            'porFormulario'   => $this->agruparPor($participantes, fn ($p) => $p->registration->form_types_id),
            'nombresFormTypes'=> $evento->formTypes()->pluck('name', 'id'),
            'estados'         => self::ESTADOS,
            // Firma cubre solo `evento` (los filtros se ignoran al validar en
            // exportCsv) — la vista arma los links filtrados agregando
            // &categoria=/&form_type_id=/&pago_status= a esta misma URL base.
            'exportBaseUrl' => URL::signedRoute('organizador.dashboard.export', ['evento' => $evento->id]),
        ]);
    }

    /**
     * Descarga CSV del listado de participantes, con filtros opcionales por
     * categoría / tipo de formulario / estado de pago (query string, sin
     * firmar) — la firma solo cubre `evento`, así el mismo link sirve para
     * cualquier combinación de filtros sin generar un link por cada uno.
     */
    public function exportCsv(Evento $evento, Request $request)
    {
        abort_unless(
            $request->hasValidSignatureWhileIgnoring(['categoria', 'form_type_id', 'pago_status']),
            403
        );

        $query = $this->participantesDelEvento($evento);

        if ($request->filled('categoria')) {
            $query->where('categoria', $request->query('categoria'));
        }
        if ($request->filled('form_type_id')) {
            $query->whereHas('registration', fn (Builder $q) => $q->where('form_types_id', $request->query('form_type_id')));
        }
        if ($request->filled('pago_status')) {
            $query->whereHas('registration', fn (Builder $q) => $q->where('pago_status', $request->query('pago_status')));
        }

        $participantes = $query->get();

        return response()->streamDownload(function () use ($participantes) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Nombre', 'Apellido', 'Documento', 'Categoría', 'Tipo de formulario',
                'Talla/Polera', 'Souvenirs', 'Teléfono', 'Correo', 'Estado de pago', 'Referencia',
            ]);
            foreach ($participantes as $p) {
                fputcsv($out, [
                    $p->nombre,
                    $p->apellido,
                    trim($p->tipo_documento . ' ' . $p->numero_documento),
                    $p->categoria,
                    optional($p->registration->formType)->name,
                    $p->polera,
                    $p->souvenirParticipante->pluck('nombre')->implode(', '),
                    $p->telefono,
                    $p->correo,
                    $p->registration->pago_status,
                    $p->registration->referencia,
                ]);
            }
            fclose($out);
        }, 'participantes-evento-' . $evento->id . '.csv', ['Content-Type' => 'text/csv']);
    }

    private function participantesDelEvento(Evento $evento): Builder
    {
        return Participante::whereHas('registration', fn (Builder $q) => $q->where('evento_id', $evento->id))
            ->with(['registration.formType', 'souvenirParticipante']);
    }

    private function contarPorEstado(Collection $participantes): array
    {
        $counts = array_fill_keys(self::ESTADOS, 0);
        foreach ($participantes as $p) {
            $estado = $p->registration->pago_status;
            if (isset($counts[$estado])) {
                $counts[$estado]++;
            }
        }
        $counts['total'] = $participantes->count();

        return $counts;
    }

    /**
     * Agrupa por el resultado de $keyFn (nombre de categoría o de tipo de
     * formulario) y cuenta por estado dentro de cada grupo.
     */
    private function agruparPor(Collection $participantes, callable $keyFn): array
    {
        $grupos = [];
        foreach ($participantes as $p) {
            $key = $keyFn($p) ?: 'Sin especificar';
            if (!isset($grupos[$key])) {
                $grupos[$key] = array_fill_keys(self::ESTADOS, 0);
                $grupos[$key]['total'] = 0;
            }
            $estado = $p->registration->pago_status;
            if (isset($grupos[$key][$estado])) {
                $grupos[$key][$estado]++;
            }
            $grupos[$key]['total']++;
        }
        ksort($grupos);

        return $grupos;
    }
}

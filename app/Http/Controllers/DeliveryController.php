<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEventoScope;
use App\Models\Evento;
use App\Models\Participante;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class DeliveryController extends Controller
{
    use AuthorizesEventoScope;

    /**
     * Estados de `participantes.estado_delivery` — ver
     * brain/PLAN-DELIVERY-31072026.md.
     */
    private const ESTADOS = ['pendiente', 'confirmado', 'entregado', 'cancelado'];

    /**
     * Dashboard privado (sin login, link firmado) para que el organizador —
     * o la empresa de delivery a la que le pase el link — vea quiénes
     * quieren el kit a domicilio y confirme/marque el envío.
     */
    public function show(Evento $evento)
    {
        $participantes = $this->participantesDelEvento($evento)->get();

        return view('organizador.delivery', [
            'evento'             => $evento,
            'participantes'      => $participantes,
            'resumen'            => $this->resumen($participantes),
            'exportBaseUrl'      => URL::signedRoute('delivery.dashboard.export', ['evento' => $evento->id]),
            'updateEstadoUrlFor' => fn (Participante $p) => URL::signedRoute(
                'delivery.dashboard.update-estado',
                ['evento' => $evento->id, 'participante' => $p->id]
            ),
        ]);
    }

    /**
     * CSV para la empresa de delivery — nombre, dirección, teléfono y
     * contenido del kit. La firma solo cubre `evento`, el filtro de estado
     * se ignora al validar (mismo patrón que OrganizadorDashboardController).
     */
    public function exportCsv(Evento $evento, Request $request)
    {
        abort_unless($request->hasValidSignatureWhileIgnoring(['estado_delivery']), 403);

        $query = $this->participantesDelEvento($evento);

        if ($request->filled('estado_delivery')) {
            $query->where('estado_delivery', $request->query('estado_delivery'));
        }

        $participantes = $query->get();

        return response()->streamDownload(function () use ($participantes) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Nombre', 'Apellido', 'Documento', 'Dirección', 'Ciudad', 'Lat', 'Lng', 'Teléfono', 'Correo',
                'Categoría', 'Talla/Polera', 'Souvenirs', 'Tipo de formulario', 'Estado de envío', 'Referencia',
            ]);
            foreach ($participantes as $p) {
                fputcsv($out, [
                    $p->nombre,
                    $p->apellido,
                    trim($p->tipo_documento . ' ' . $p->numero_documento),
                    $p->direccion,
                    $p->ciudad,
                    $p->delivery_lat,
                    $p->delivery_lng,
                    $p->telefono,
                    $p->correo,
                    $p->categoria,
                    $p->polera,
                    $p->souvenirParticipante->pluck('nombre')->implode(', '),
                    optional($p->registration->formType)->name,
                    $p->estado_delivery,
                    $p->registration->referencia,
                ]);
            }
            fclose($out);
        }, 'delivery-evento-' . $evento->id . '.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * Avanza el estado de envío de un participante (pendiente → confirmado
     * → entregado, o cancelado). La firma cubre evento+participante; el
     * estado destino viaja como querystring sin firmar (?estado=), mismo
     * patrón de "ignorar filtros" que el CSV. Responde JSON si el cliente
     * lo pide (integración de la empresa de delivery), si no redirige al
     * dashboard HTML (link clickeado por una persona).
     */
    public function updateEstado(Request $request, Evento $evento, Participante $participante)
    {
        abort_unless($request->hasValidSignatureWhileIgnoring(['estado']), 403);
        abort_unless($participante->registration->evento_id === $evento->id, 404);

        $estado = $request->query('estado');
        abort_unless(in_array($estado, self::ESTADOS, true), 422);

        $participante->update(['estado_delivery' => $estado]);

        if ($request->wantsJson()) {
            return response()->json([
                'success'        => true,
                'participanteId' => $participante->id,
                'estadoDelivery' => $estado,
            ]);
        }

        return redirect()->to(URL::signedRoute('delivery.dashboard', ['evento' => $evento->id]));
    }

    /**
     * Link de consumo (JSON) para que la empresa de delivery integre esta
     * información a su propio sistema, en vez de depender de leer la
     * página HTML o el CSV a mano — mismo listado que el dashboard, más el
     * link firmado por participante para que ellos mismos puedan marcar
     * "entregado" desde su sistema (POST/GET con Accept: application/json
     * contra `actualizarEstadoUrl` + `&estado=entregado`).
     */
    public function json(Evento $evento)
    {
        $participantes = $this->participantesDelEvento($evento)->get();

        return response()->json([
            'success' => true,
            'evento'  => [
                'id'          => $evento->id,
                'nombre'      => $evento->nombre,
                'fechaInicio' => $evento->fecha_inicio,
            ],
            'resumen' => $this->resumen($participantes),
            'participantes' => $participantes->map(fn (Participante $p) => [
                'id'                  => $p->id,
                'nombre'              => $p->nombre,
                'apellido'            => $p->apellido,
                'documento'           => trim($p->tipo_documento . ' ' . $p->numero_documento),
                'direccion'           => $p->direccion,
                'ciudad'              => $p->ciudad,
                // Mapa de ubicación (12/08/2026) — pin opcional que el
                // participante marcó en elascenso/event; null si no lo tocó.
                'lat'                 => $p->delivery_lat !== null ? (float) $p->delivery_lat : null,
                'lng'                 => $p->delivery_lng !== null ? (float) $p->delivery_lng : null,
                'telefono'            => $p->telefono,
                'correo'              => $p->correo,
                'categoria'           => $p->categoria,
                'talla'               => $p->polera,
                'souvenirs'           => $p->souvenirParticipante->pluck('nombre')->values(),
                'tipoFormulario'      => optional($p->registration->formType)->name,
                'referencia'          => $p->registration->referencia,
                'estadoDelivery'      => $p->estado_delivery,
                'actualizarEstadoUrl' => URL::signedRoute(
                    'delivery.dashboard.update-estado',
                    ['evento' => $evento->id, 'participante' => $p->id]
                ),
            ])->values(),
        ]);
    }

    /**
     * Mapa de ubicación de delivery (12/08/2026) — vista para el
     * organizador dentro del panel de administración (admin-eventos), a
     * diferencia de json()/exportCsv()/show() que son para la empresa de
     * delivery externa (sin login, link firmado). Mismo listado y mismo
     * criterio de permisos que ListaEsperaController/ParticipanteController
     * ::porEvento() — super_admin o el admin scoped a este evento. No
     * incluye `actualizarEstadoUrl`: esta vista es de solo lectura, el
     * admin no opera el estado del envío desde acá.
     */
    public function indexForAdmin(Evento $event): JsonResponse
    {
        $this->assertCanWriteEvento($event->id);

        $participantes = $this->participantesDelEvento($event)->get();

        return response()->json([
            'success' => true,
            'resumen' => $this->resumen($participantes),
            'participantes' => $participantes->map(fn (Participante $p) => [
                'id'             => $p->id,
                'nombre'         => $p->nombre,
                'apellido'       => $p->apellido,
                'documento'      => trim($p->tipo_documento . ' ' . $p->numero_documento),
                'direccion'      => $p->direccion,
                'ciudad'         => $p->ciudad,
                'lat'            => $p->delivery_lat !== null ? (float) $p->delivery_lat : null,
                'lng'            => $p->delivery_lng !== null ? (float) $p->delivery_lng : null,
                'telefono'       => $p->telefono,
                'correo'         => $p->correo,
                'categoria'      => $p->categoria,
                'talla'          => $p->polera,
                'souvenirs'      => $p->souvenirParticipante->pluck('nombre')->values(),
                'tipoFormulario' => optional($p->registration->formType)->name,
                'referencia'     => $p->registration->referencia,
                'estadoDelivery' => $p->estado_delivery,
            ])->values(),
        ]);
    }

    private function participantesDelEvento(Evento $evento): Builder
    {
        return Participante::whereHas('registration', fn (Builder $q) => $q->where('evento_id', $evento->id))
            ->where('quiere_delivery', true)
            ->with(['registration.formType', 'souvenirParticipante']);
    }

    private function resumen(Collection $participantes): array
    {
        $counts = array_fill_keys(self::ESTADOS, 0);
        foreach ($participantes as $p) {
            if (isset($counts[$p->estado_delivery])) {
                $counts[$p->estado_delivery]++;
            }
        }
        $counts['total'] = $participantes->count();

        return $counts;
    }
}

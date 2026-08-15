<?php

namespace App\Support;

use App\Models\Evento;
use App\Models\Participante;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Conteo de inscripciones de un evento (total/por estado de pago, agrupado
 * por categoría y por tipo de formulario) — extraído de
 * OrganizadorDashboardController para reusar exactamente el mismo cálculo
 * tanto en el dashboard por correo (link firmado, sin login) como en el
 * endpoint autenticado del panel de administración
 * (EventoController::dashboardInscripciones).
 */
class DashboardInscripcionesData
{
    /**
     * Los 4 estados reales de `registrations.pago_status` (migración
     * create_registrations_table) — se muestran todos por separado, sin
     * fusionar "failed" con "cancelled". Público (15/08/2026) para que
     * ParticipanteController::porEvento() valide su filtro `pago_status`
     * contra la misma lista, sin duplicarla.
     */
    public const ESTADOS = ['paid', 'pending', 'cancelled', 'failed'];

    public static function paraEvento(Evento $evento): array
    {
        $participantes = self::participantesDelEvento($evento)->get();

        return [
            'totalGeneral'     => self::contarPorEstado($participantes),
            // Se agrupa por el id de categoría (no por nombre) por el mismo
            // motivo que porFormulario — el nombre se resuelve en la vista
            // vía nombresCategorias, igual que nombresFormTypes.
            'porCategoria'     => self::agruparPor($participantes, fn ($p) => $p->categoria),
            // Se agrupa por form_types_id (no por nombre) para poder armar el
            // link de descarga filtrado directo con ese id.
            'porFormulario'    => self::agruparPor($participantes, fn ($p) => $p->registration->form_types_id),
            'nombresCategorias' => $evento->categories()->pluck('name', 'id'),
            'nombresFormTypes' => $evento->formTypes()->pluck('name', 'id'),
            'estados'          => self::ESTADOS,
        ];
    }

    public static function participantesDelEvento(Evento $evento): Builder
    {
        // .totals eager-cargado (12/08/2026) para el cobro en sitio del CSV
        // export (OrganizadorDashboardController::exportCsv) — evita N+1;
        // no lo usa ningún otro llamador hoy pero es gratis mantenerlo acá.
        return Participante::whereHas('registration', fn (Builder $q) => $q->where('evento_id', $evento->id))
            ->with(['registration.formType', 'registration.totals', 'souvenirParticipante']);
    }

    private static function contarPorEstado(Collection $participantes): array
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
    private static function agruparPor(Collection $participantes, callable $keyFn): array
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

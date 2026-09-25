<?php

namespace App\Support;

use App\Models\EmpresaExpositora;
use App\Models\LeadCapturado;

/**
 * SmartStand (25/09/2026) — estadísticas de los leads capturados por una
 * empresa expositora. Clase de solo cálculo (mismo patrón que
 * DashboardInscripcionesData/BalanceEventoData), reusada por el endpoint del
 * propio expositor y por la pantalla del organizador.
 *
 * Solo usa datos que ya existen estructurados (`capturado_at`,
 * `participantes.ciudad`). El "mapa de especialidades" del brochure queda
 * fuera: la especialidad no es un campo estructurado hoy.
 */
class LeadsCapturadosData
{
    public static function paraEmpresa(EmpresaExpositora $empresa): array
    {
        $base = fn () => LeadCapturado::where('leads_capturados.empresa_expositora_id', $empresa->id);

        $porDia = $base()
            ->selectRaw('DATE(leads_capturados.capturado_at) as fecha, COUNT(*) as total')
            ->groupBy('fecha')
            ->orderBy('fecha')
            ->get()
            ->map(fn ($r) => ['fecha' => (string) $r->fecha, 'total' => (int) $r->total])
            ->all();

        $porCiudad = $base()
            ->join('participantes', 'participantes.id', '=', 'leads_capturados.participante_id')
            // Alias distinto de la columna (`ciudad_grupo`): con el alias
            // `ciudad`, MySQL agrupa por la columna real sin el TRIM.
            ->selectRaw("COALESCE(NULLIF(TRIM(participantes.ciudad), ''), 'Sin dato') as ciudad_grupo, COUNT(*) as total")
            ->groupBy('ciudad_grupo')
            ->orderByDesc('total')
            ->orderBy('ciudad_grupo')
            ->limit(10)
            ->get()
            ->map(fn ($r) => ['ciudad' => (string) $r->ciudad_grupo, 'total' => (int) $r->total])
            ->all();

        $promedio = $base()->whereNotNull('calificacion')->avg('calificacion');

        return [
            'totalLeads'           => $base()->count(),
            'porDia'               => $porDia,
            'porCiudad'            => $porCiudad,
            'calificacionPromedio' => $promedio === null ? null : round((float) $promedio, 2),
        ];
    }
}

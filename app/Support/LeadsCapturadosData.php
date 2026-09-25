<?php

namespace App\Support;

use App\Models\Answer;
use App\Models\EmpresaExpositora;
use App\Models\Evento;
use App\Models\FormularioCampos;
use App\Models\LeadCapturado;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * SmartStand (25/09/2026) — estadísticas de los leads capturados por una
 * empresa expositora. Clase de solo cálculo (mismo patrón que
 * DashboardInscripcionesData/BalanceEventoData), reusada por el endpoint del
 * propio expositor y por la pantalla del organizador.
 *
 * Fase 4 (26/09/2026): especialidad e institución. No son campos estructurados
 * del participante: son respuestas a preguntas del formulario que el organizador
 * indica en `eventos.expositores_config` (`especialidad_pregunta` /
 * `institucion_pregunta`, la ETIQUETA de la pregunta). Se guarda la etiqueta y no
 * un id porque cada tipo de formulario tiene su propia pregunta "Especialidad".
 */
class LeadsCapturadosData
{
    public const TOP = 10;

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

        $evento = $empresa->evento;
        $campos = self::camposConfigurados($evento);
        $porEspecialidad = [];
        $porInstitucion = [];
        if ($campos['especialidad'] !== null || $campos['institucion'] !== null) {
            $ids = $base()->pluck('participante_id');
            $respuestas = self::respuestasDe($evento, $ids);
            $porEspecialidad = $campos['especialidad'] !== null ? self::agrupar(self::columna($ids, $respuestas, 'especialidad')) : [];
            $porInstitucion = $campos['institucion'] !== null ? self::agrupar(self::columna($ids, $respuestas, 'institucion')) : [];
        }

        return [
            'totalLeads'               => $base()->count(),
            'porDia'                   => $porDia,
            'porCiudad'                => $porCiudad,
            'calificacionPromedio'     => $promedio === null ? null : round((float) $promedio, 2),
            'especialidadConfigurada'  => $campos['especialidad'] !== null,
            'institucionConfigurada'   => $campos['institucion'] !== null,
            'porEspecialidad'          => $porEspecialidad,
            'porInstitucion'           => $porInstitucion,
        ];
    }

    /** Etiquetas de las preguntas que el organizador marcó (null = sin configurar). */
    public static function camposConfigurados(?Evento $evento): array
    {
        $config = $evento?->expositores_config ?? [];
        $limpio = fn ($v) => is_string($v) && trim($v) !== '' ? trim($v) : null;

        return [
            'especialidad' => $limpio($config['especialidad_pregunta'] ?? null),
            'institucion'  => $limpio($config['institucion_pregunta'] ?? null),
        ];
    }

    /**
     * Respuestas de especialidad/institución de un conjunto de participantes.
     * Devuelve [participante_id => ['especialidad' => '…', 'institucion' => '…']],
     * con solo las claves que tengan respuesta. Vacío si el organizador no
     * configuró ninguna pregunta.
     *
     * @param  Collection<int, int|string>  $participanteIds
     * @return array<int, array<string, string>>
     */
    public static function respuestasDe(?Evento $evento, Collection $participanteIds): array
    {
        $campos = array_filter(self::camposConfigurados($evento));
        if (! $evento || $campos === [] || $participanteIds->isEmpty()) {
            return [];
        }

        // pregunta (id) => 'especialidad'|'institucion', comparando por etiqueta normalizada.
        $porEtiqueta = array_map([self::class, 'normalizar'], $campos);
        $clavePorPregunta = FormularioCampos::whereIn('form_types_id', $evento->formTypes()->pluck('id'))
            ->get(['id', 'etiqueta'])
            ->mapWithKeys(function ($pregunta) use ($porEtiqueta) {
                $clave = array_search(self::normalizar((string) $pregunta->etiqueta), $porEtiqueta, true);

                return $clave === false ? [] : [$pregunta->id => $clave];
            });

        if ($clavePorPregunta->isEmpty()) {
            return [];
        }

        $resultado = [];
        Answer::whereIn('question_id', $clavePorPregunta->keys())
            ->whereIn('participante_id', $participanteIds->unique()->values())
            ->get(['participante_id', 'question_id', 'value'])
            ->each(function ($respuesta) use ($clavePorPregunta, &$resultado) {
                $valor = trim((string) $respuesta->value);
                if ($valor !== '') {
                    $resultado[(int) $respuesta->participante_id][$clavePorPregunta[$respuesta->question_id]] = $valor;
                }
            });

        return $resultado;
    }

    /** Minúsculas, sin acentos y con los espacios colapsados: la clave de agrupación. */
    public static function normalizar(string $valor): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', Str::of($valor)->lower()->ascii()->toString()));
    }

    /**
     * Cuenta valores agrupando por su forma normalizada; la etiqueta que se
     * muestra es la grafía más frecuente del grupo. Vacíos → "Sin dato". Top 10
     * más una fila "Otros" con el resto.
     *
     * @param  array<int, string|null>  $valores
     * @return array<int, array{etiqueta: string, total: int}>
     */
    public static function agrupar(array $valores): array
    {
        $grupos = [];
        foreach ($valores as $valor) {
            $texto = trim((string) $valor);
            $clave = $texto === '' ? '' : self::normalizar($texto);
            $grupos[$clave]['total'] = ($grupos[$clave]['total'] ?? 0) + 1;
            if ($texto !== '') {
                $grupos[$clave]['grafias'][$texto] = ($grupos[$clave]['grafias'][$texto] ?? 0) + 1;
            }
        }

        $filas = [];
        foreach ($grupos as $clave => $grupo) {
            if ($clave === '') {
                continue;
            }
            arsort($grupo['grafias']);
            $filas[] = ['etiqueta' => (string) array_key_first($grupo['grafias']), 'total' => $grupo['total']];
        }
        usort($filas, fn ($a, $b) => [$b['total'], $a['etiqueta']] <=> [$a['total'], $b['etiqueta']]);

        $resultado = array_slice($filas, 0, self::TOP);
        $resto = array_sum(array_column(array_slice($filas, self::TOP), 'total'));
        if ($resto > 0) {
            $resultado[] = ['etiqueta' => 'Otros', 'total' => $resto];
        }
        if (isset($grupos[''])) {
            $resultado[] = ['etiqueta' => 'Sin dato', 'total' => $grupos['']['total']];
        }

        return $resultado;
    }

    /**
     * Una respuesta por lead (null si ese participante no respondió), para que
     * quienes no contestaron cuenten en "Sin dato" en vez de desaparecer.
     *
     * @param  Collection<int, int|string>  $participanteIds
     */
    private static function columna(Collection $participanteIds, array $respuestas, string $clave): array
    {
        return $participanteIds->map(fn ($id) => $respuestas[(int) $id][$clave] ?? null)->all();
    }
}

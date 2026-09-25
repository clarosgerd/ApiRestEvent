<?php

namespace App\Http\Controllers;

use App\Actions\RealizarSorteoAction;
use App\Models\EmpresaExpositora;
use App\Models\LeadCapturado;
use App\Models\Sorteo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * SmartStand fase 4 — sorteo digital de una empresa expositora entre SUS
 * propios contactos (guard `expositores`). No envía nada a nadie: solo elige y
 * deja el registro (premio, ganador, cuántos participaban, sobre qué lista).
 */
class ExpositorSorteoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $cuenta = $this->cuenta($request);

        $sorteos = Sorteo::where('empresa_expositora_id', $cuenta->id)
            ->orderByDesc('sorteado_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $sorteos->map(fn (Sorteo $s) => RealizarSorteoAction::presentar($s))->values(),
        ]);
    }

    public function store(Request $request, RealizarSorteoAction $sorteo): JsonResponse
    {
        $cuenta = $this->cuenta($request);

        $data = $request->validate([
            'premio'            => ['required', 'string', 'max:150'],
            'min_calificacion'  => ['nullable', 'integer', 'between:1,5'],
            'excluir_ganadores' => ['sometimes', 'boolean'],
        ], [
            'premio.required'         => 'Escribe el premio que vas a sortear.',
            'premio.max'              => 'El premio puede tener hasta 150 caracteres.',
            'min_calificacion.between' => 'La calificación mínima debe estar entre 1 y 5.',
        ]);
        $excluirGanadores = $data['excluir_ganadores'] ?? true;
        $minCalificacion = $data['min_calificacion'] ?? null;

        $candidatos = LeadCapturado::where('empresa_expositora_id', $cuenta->id)
            ->when($minCalificacion, fn ($q) => $q->where('calificacion', '>=', $minCalificacion))
            ->pluck('participante_id');

        if ($excluirGanadores) {
            $yaGanaron = Sorteo::where('empresa_expositora_id', $cuenta->id)->pluck('participante_ganador_id');
            $candidatos = $candidatos->diff($yaGanaron);
        }

        $resultado = $sorteo->ejecutar(
            $cuenta->evento,
            Sorteo::TIPO_EXPOSITOR,
            $candidatos,
            trim($data['premio']),
            ['min_calificacion' => $minCalificacion, 'excluir_ganadores' => $excluirGanadores],
            $cuenta,
        );

        if ($resultado === null) {
            return response()->json([
                'success' => false,
                'error'   => $minCalificacion || $excluirGanadores
                    ? 'No hay contactos disponibles para sortear con esos filtros (quizás todos ya ganaron o ninguno alcanza la calificación).'
                    : 'Todavía no capturaste contactos para sortear.',
            ], 422);
        }

        return response()->json(['success' => true, 'sorteo' => RealizarSorteoAction::presentar($resultado)], 201);
    }

    private function cuenta(Request $request): EmpresaExpositora
    {
        return $request->user('expositores');
    }
}

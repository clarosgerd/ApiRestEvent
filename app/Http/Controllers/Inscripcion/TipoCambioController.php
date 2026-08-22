<?php

namespace App\Http\Controllers\Inscripcion;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Consolidación monolito (22/08/2026), Fase 2 — port 1:1 de
 * `elascenso-blade\App\Http\Controllers\Api\TipoCambioController`. No es
 * proxy de ApiRestEvent (consulta una API pública de tipo de cambio aparte),
 * así que no había ningún `forward()` que eliminar — el único cambio real es
 * el namespace. Los montos siempre se cobran en BOB; esto es solo para
 * mostrar un equivalente aproximado en USD/BRL.
 *
 * `CACHE_KEY` pública porque el store de inscripciones en USD (Fase 2b, ver
 * `Inscripcion\RegistroProxyController` cuando se porte) reusa esta misma
 * clave de cache para leer la tasa aplicada — misma fuente de verdad que ya
 * usaba `elascenso-blade` (ver `RegistroProxyController::store()` original).
 */
class TipoCambioController extends Controller
{
    private const TTL_SECONDS = 7200; // 2 horas

    private const EXCHANGE_RATE_API = 'https://open.er-api.com/v6/latest/BOB';

    private const FALLBACK_USD = 0.1437;

    private const FALLBACK_BRL = 0.72;

    public const CACHE_KEY = 'tipo_cambio';

    public function show()
    {
        $cached = Cache::get(self::CACHE_KEY);

        if ($cached && (time() - $cached['timestamp']) <= self::TTL_SECONDS) {
            return response()->json(['success' => true, 'base' => 'BOB', 'rates' => $cached['rates'], 'source' => 'cache']);
        }

        $rates = $this->consultarApiExterna();
        if ($rates) {
            Cache::forever(self::CACHE_KEY, ['timestamp' => time(), 'rates' => $rates]);

            return response()->json(['success' => true, 'base' => 'BOB', 'rates' => $rates, 'source' => 'live']);
        }

        if ($cached) {
            return response()->json(['success' => true, 'base' => 'BOB', 'rates' => $cached['rates'], 'source' => 'cache']);
        }

        return response()->json([
            'success' => true,
            'base' => 'BOB',
            'rates' => ['USD' => self::FALLBACK_USD, 'BRL' => self::FALLBACK_BRL],
            'source' => 'fallback',
        ]);
    }

    /** @return array{USD: float, BRL: float}|null */
    private function consultarApiExterna(): ?array
    {
        try {
            $response = Http::timeout(5)->get(self::EXCHANGE_RATE_API);
        } catch (\Throwable) {
            return null;
        }

        $data = $response->json();
        if (($data['result'] ?? '') !== 'success' || empty($data['rates']['USD']) || empty($data['rates']['BRL'])) {
            return null;
        }

        return ['USD' => (float) $data['rates']['USD'], 'BRL' => (float) $data['rates']['BRL']];
    }
}

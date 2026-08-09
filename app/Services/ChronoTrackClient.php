<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente de solo lectura contra la API pública de ChronoTrack Live
 * (https://api.chronotrack.com/dev/docs). No creamos ni administramos nada
 * ahí — el organizador registra la carrera y obtiene el `event_id` de
 * ChronoTrack por su cuenta; nosotros solo leemos resultados ya generados.
 *
 * Auth: "Test Auth Scheme" documentado por ChronoTrack —
 * `client_id` + `user_id` (el login admin de ChronoTrack, no el nuestro) +
 * `user_pass` (SHA1 de la contraseña real, nunca en texto plano en la URL).
 * Verificado en vivo el 09/08/2026 contra el evento real 93491
 * ("DESAFIO DE LOS PUENTES 2026", Cronochip SRL.) — ver
 * brain/groovy-chasing-ladybug.md.
 */
class ChronoTrackClient
{
    private string $baseUrl;
    private ?string $clientId;
    private ?string $userId;
    private ?string $userPass;

    public function __construct()
    {
        $config = config('services.chronotrack');
        $this->baseUrl = rtrim($config['base_url'], '/');
        $this->clientId = $config['client_id'];
        $this->userId = $config['user_id'];
        $this->userPass = $config['user_pass'];
    }

    /**
     * Todos los intervals de un evento (completos y parciales/checkpoints),
     * uno o más por carrera/distancia. Cada elemento trae al menos
     * `interval_id`, `interval_is_full`, `race_id`, `race_name`.
     */
    public function intervalsDeEvento(string $chronotrackEventId): array
    {
        $response = $this->get("/event/{$chronotrackEventId}/interval");

        return $response['event_interval'] ?? [];
    }

    /**
     * Solo los intervals que representan la distancia completa de cada
     * carrera (no splits/checkpoints parciales) — uno por carrera/distancia.
     */
    public function intervalsCompletos(string $chronotrackEventId): array
    {
        return array_values(array_filter(
            $this->intervalsDeEvento($chronotrackEventId),
            fn (array $interval) => ($interval['interval_is_full'] ?? '0') === '1'
        ));
    }

    /**
     * Intervals parciales/checkpoints (splits intermedios, ej. "PC1") de un
     * evento — usados solo para distinguir DNF (llegó a un checkpoint pero
     * no terminó) de DNS (nunca se lo vio en ningún timing point). No todas
     * las carreras tienen — algunas solo miden la distancia completa.
     */
    public function intervalsParciales(string $chronotrackEventId): array
    {
        return array_values(array_filter(
            $this->intervalsDeEvento($chronotrackEventId),
            fn (array $interval) => ($interval['interval_is_full'] ?? '0') !== '1'
        ));
    }

    /**
     * Resultados (finishers) de un interval, paginados. ChronoTrack pagina
     * con `page`/`size` — se sigue pidiendo página tras página hasta que
     * viene una vacía, devolviendo la lista completa ya "aplanada".
     */
    public function resultadosDeInterval(string $intervalId, int $pageSize = 200): array
    {
        return $this->paginar("/interval/{$intervalId}/results", 'interval_results', $pageSize);
    }

    /**
     * Entries (inscriptos con bib asignado) de una carrera — para detectar
     * DNS/DNF hay que saber quién se inscribió, no solo quién tiene tiempo.
     */
    public function entriesDeCarrera(string $raceId, int $pageSize = 200): array
    {
        return $this->paginar("/race/{$raceId}/entry", 'race_entry', $pageSize);
    }

    private function paginar(string $path, string $key, int $pageSize): array
    {
        $todos = [];
        $page = 1;

        do {
            $response = $this->get($path, [
                'page' => $page,
                'size' => $pageSize,
            ]);
            $pagina = $response[$key] ?? [];
            $todos = array_merge($todos, $pagina);
            $page++;
        } while (count($pagina) === $pageSize);

        return $todos;
    }

    private function get(string $path, array $query = []): array
    {
        if (!$this->clientId || !$this->userId || !$this->userPass) {
            throw new \RuntimeException(
                'Faltan credenciales de ChronoTrack (CHRONOTRACK_CLIENT_ID/USER_ID/USER_PASS en .env).'
            );
        }

        $response = Http::get($this->baseUrl . $path, array_merge([
            'client_id' => $this->clientId,
            'user_id'   => $this->userId,
            'user_pass' => sha1($this->userPass),
            'format'    => 'json',
        ], $query));

        if ($response->failed()) {
            Log::warning('ChronoTrackClient: request fallida', [
                'path'   => $path,
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            $response->throw();
        }

        return $response->json() ?? [];
    }
}

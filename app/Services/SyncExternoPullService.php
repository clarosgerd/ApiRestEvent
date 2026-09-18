<?php

namespace App\Services;

use App\Actions\SincronizarParticipanteExternoAction;
use App\Models\EventoSyncExternoConfig;
use App\Models\FormType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

/**
 * Sync periódico (pull) de participantes de un evento con registro propio
 * externo (17/09/2026) — a diferencia de SyncExternoController (COLABIOCLI,
 * push: ellos nos llaman), acá SOMOS nosotros quienes llamamos a la URL que
 * la fuente expone, con el token guardado en `EventoSyncExternoConfig`.
 * Mismo idioma que App\Services\ChronoTrackClient (Http:: facade,
 * credenciales desde el registro de config, no hardcodeadas).
 *
 * Reusa `SincronizarParticipanteExternoAction::run()` sin tocarla — una
 * fila mala no tumba el resto (mismo criterio que el webhook). El
 * `form_type` por participante se resuelve ACÁ, no dentro del Action (ver
 * plan) — así `run()` no se entera de que existe este campo.
 */
class SyncExternoPullService
{
    public function __construct(private SincronizarParticipanteExternoAction $action)
    {
    }

    /**
     * @return array{creados: int, actualizados: int, omitidos: array, error: ?string}
     */
    public function sincronizar(EventoSyncExternoConfig $config): array
    {
        $resumen = ['creados' => 0, 'actualizados' => 0, 'omitidos' => [], 'error' => null];

        try {
            $pending = Http::timeout(30);
            if ($config->token) {
                $pending = $pending->withToken($config->token);
            }
            $response = $pending->get($config->url);
        } catch (\Throwable $e) {
            $resumen['error'] = 'No se pudo conectar con la fuente: ' . $e->getMessage();
            $this->guardarResultado($config, $resumen);

            return $resumen;
        }

        if ($response->failed()) {
            $resumen['error'] = "La fuente respondió con error HTTP {$response->status()}.";
            $this->guardarResultado($config, $resumen);

            return $resumen;
        }

        $validator = Validator::make($response->json() ?? [], [
            'participantes' => ['required', 'array'],
            'participantes.*.nombre' => ['nullable', 'string', 'max:255'],
            'participantes.*.apellido' => ['nullable', 'string', 'max:255'],
            'participantes.*.correo' => ['nullable', 'string', 'max:255'],
            'participantes.*.telefono' => ['nullable', 'string', 'max:50'],
            'participantes.*.categoria' => ['nullable', 'string', 'max:255'],
            'participantes.*.numero_documento' => ['nullable', 'string', 'max:255'],
            'participantes.*.tipo_documento' => ['nullable', 'string', 'max:50'],
            'participantes.*.genero' => ['nullable', 'string', 'max:50'],
            'participantes.*.fecha_nacimiento' => ['nullable', 'string', 'max:30'],
            'participantes.*.form_type' => ['nullable', 'string', 'max:255'],
            'participantes.*.souvenirs' => ['nullable', 'array'],
            'participantes.*.souvenirs.*.nombre' => ['nullable', 'string', 'max:255'],
            'participantes.*.souvenirs.*.talla' => ['nullable', 'string', 'max:30'],
            'participantes.*.souvenirs.*.sexo' => ['nullable', 'string', 'max:30'],
            'participantes.*.talleres' => ['nullable', 'array'],
            'participantes.*.talleres.*.taller' => ['nullable', 'string', 'max:255'],
            'participantes.*.talleres.*.sesion' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            $resumen['error'] = 'JSON con forma inválida: ' . $validator->errors()->first();
            $this->guardarResultado($config, $resumen);

            return $resumen;
        }

        $data = $validator->validated();
        $formTypesPorNombre = $config->evento->formTypes()->get()
            ->keyBy(fn ($ft) => mb_strtolower(trim($ft->name)));

        foreach ($data['participantes'] as $i => $fila) {
            $formType = $this->resolverFormType($fila, $formTypesPorNombre, $config);

            $resultado = $this->action->run($config->evento, $formType, $fila);

            match ($resultado['resultado']) {
                'creado' => $resumen['creados']++,
                'actualizado' => $resumen['actualizados']++,
                'omitido' => $resumen['omitidos'][] = ['fila' => $i, 'motivo' => $resultado['motivo']],
            };
        }

        $this->guardarResultado($config, $resumen);

        return $resumen;
    }

    /**
     * @param array<string, mixed> $fila
     * @param Collection<string, FormType> $formTypesPorNombre
     */
    private function resolverFormType(array $fila, Collection $formTypesPorNombre, EventoSyncExternoConfig $config): FormType
    {
        $nombre = mb_strtolower(trim((string) ($fila['form_type'] ?? '')));

        if ($nombre !== '' && $formTypesPorNombre->has($nombre)) {
            return $formTypesPorNombre->get($nombre);
        }

        return $config->formType;
    }

    private function guardarResultado(EventoSyncExternoConfig $config, array $resumen): void
    {
        $config->update([
            'ultima_sincronizacion_at' => now(),
            'ultimo_resultado' => $resumen,
        ]);
    }
}

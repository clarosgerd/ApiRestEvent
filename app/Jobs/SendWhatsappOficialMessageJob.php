<?php

namespace App\Jobs;

use App\Models\WhatsappCuenta;
use App\Services\WhatsappCloudApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp Business API oficial por organizador (08/10/2026) — mismo
 * esqueleto de reintentos/backoff que SendWhatsappMessageJob (canal
 * openwa), adaptado a la API real de Meta. Recibe el id de la cuenta (no
 * el modelo completo) y lo vuelve a resolver dentro de handle() — mismo
 * motivo que cualquier Job serializado: no viajar con el access_token en
 * la carga serializada de la cola más tiempo del necesario.
 */
class SendWhatsappOficialMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [10, 30, 60];

    public function __construct(
        protected int $cuentaId,
        protected string $telefonoDigitos,
        protected string $texto,
    ) {
    }

    public function handle(WhatsappCloudApiService $service): void
    {
        $cuenta = WhatsappCuenta::find($this->cuentaId);
        if (!$cuenta || !$cuenta->activo) {
            // La cuenta se borró o se desactivó entre que se encoló el job
            // y que se procesó — no tiene sentido reintentar.
            Log::warning("SendWhatsappOficialMessageJob: cuenta {$this->cuentaId} no existe o está inactiva, se descarta.");

            return;
        }

        try {
            $service->enviarTemplate($cuenta, $this->telefonoDigitos, $this->texto);
            Log::info("WhatsApp oficial enviado a {$this->telefonoDigitos} (cuenta {$this->cuentaId}).");
        } catch (\RuntimeException $e) {
            // 401/403 (token vencido o revocado) no tiene sentido
            // reintentar — el próximo intento va a fallar igual hasta que
            // alguien actualice el token desde el panel.
            if (in_array($e->getCode(), [401, 403], true)) {
                Log::warning("WhatsApp oficial [{$e->getCode()}] para cuenta {$this->cuentaId} — token probablemente vencido, no se reintenta: {$e->getMessage()}");
                $this->fail($e);

                return;
            }

            // Resto de errores (red, 429, 5xx de Meta) — tiene sentido
            // reintentar, deja que Laravel lo haga según $tries/$backoff.
            Log::error("Error WhatsApp oficial [{$e->getCode()}] para cuenta {$this->cuentaId}: {$e->getMessage()}");
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("Job de WhatsApp oficial falló definitivamente (cuenta {$this->cuentaId}): {$exception->getMessage()}");
    }
}

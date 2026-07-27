<?php


namespace App\Jobs;
//require 'vendor/autoload.php';

use OpenWA\Client;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use OpenWA\Exceptions\OpenWARateLimitException;
use OpenWA\Exceptions\OpenWAConflictException;
use OpenWA\Exceptions\OpenWAApiException;

class SendWhatsappMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [10, 30, 60]; // backoff progresivo

    protected string $chatId; // formato: 628123456789@c.us
    protected string $texto;

    public function __construct(string $chatId, string $texto)
    {
        $this->chatId = $chatId;
        $this->texto = $texto;
    }

    public function handle(Client $client): void
    {
        $sessionId = config('services.openwa.session_id');

        try {
            $result = $client->messages->sendText($sessionId, [
                'chatId' => $this->chatId,
                'text' => $this->texto,
            ]);

            Log::info("WhatsApp enviado a {$this->chatId}, messageId: {$result['messageId']}");
        } catch (OpenWAConflictException $e) {
            // 409: la sesión existe pero no está "ready" (falta escanear QR o se cayó)
            Log::warning("Sesión OpenWA no está lista: {$e->getMessage()}");
            $this->fail($e); // no tiene sentido reintentar si no hay sesión activa
        } catch (OpenWARateLimitException $e) {
            // 429: reintenta más tarde, respetando el release()
            $this->release(30);
        } catch (OpenWAApiException $e) {
            // Confirmado empíricamente el 26/07/2026: esta instancia de OpenWA a
            // veces entrega el mensaje real a WhatsApp y RECIÉN DESPUÉS devuelve
            // 500 al construir la respuesta (bug del servidor, no nuestro) — se
            // probó mandando a un número real y el mensaje llegó pese al 500.
            // Reintentar acá reenviaría el mismo mensaje real 2-3 veces a una
            // persona. Se marca como fallido para revisión manual, sin reintentar.
            if ($e->getStatus() === 500) {
                Log::warning("OpenWA devolvió 500 para {$this->chatId} — el mensaje puede haberse entregado igual (ver nota en el código). No se reintenta para evitar duplicados: {$e->getMessage()}");
                $this->fail($e);
                return;
            }

            // Cualquier otro error tipado del SDK (401, 403, 404, 501...) — acá sí
            // asumimos que el mensaje no salió, tiene sentido reintentar.
            Log::error("Error OpenWA [{$e->getStatus()}]: ".json_encode($e->getBody()));
            throw $e; // deja que Laravel reintente según $tries
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("Job de WhatsApp falló definitivamente: {$exception->getMessage()}");
    }
}
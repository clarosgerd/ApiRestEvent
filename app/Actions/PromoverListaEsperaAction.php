<?php

namespace App\Actions;

use App\Mail\ListaEsperaPromovidaMail;
use App\Models\Evento;
use App\Models\FormType;
use App\Models\ListaEspera;
use App\Models\Souvenir;
use App\Support\DisponibilidadItemData;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Promoción automática de lista de espera — ver
 * PRD-kit-tallas-stock-lista-espera.md. Notifica, no reserva: se le
 * manda un correo a los primeros de la fila (FIFO por `created_at`)
 * hasta agotar el hueco disponible ahora mismo, y es
 * primero-en-inscribirse-primero-en-servir después del aviso — no hay
 * hold temporal (ver la decisión de diseño del PRD).
 *
 * Igual que EnviarCertificadosCongresoAction: la fila solo pasa a
 * `promovido` si el envío de correo tuvo éxito — un fallo de SMTP
 * puntual queda `pendiente` y se reintenta la próxima corrida.
 */
class PromoverListaEsperaAction
{
    public function handle(): int
    {
        $promovidos = 0;

        $grupos = ListaEspera::where('estado', 'pendiente')
            ->select('form_types_id', 'souvenir_id', 'talla', 'sexo')
            ->distinct()
            ->get();

        foreach ($grupos as $grupo) {
            $promovidos += $this->promoverGrupo(
                $grupo->form_types_id,
                $grupo->souvenir_id,
                $grupo->talla,
                $grupo->sexo
            );
        }

        return $promovidos;
    }

    private function promoverGrupo(int $formTypeId, ?int $souvenirId, ?string $talla, ?string $sexo): int
    {
        $formType = FormType::find($formTypeId);
        if (!$formType) {
            return 0;
        }

        if ($souvenirId) {
            $souvenir = Souvenir::find($souvenirId);
            if (!$souvenir) {
                return 0;
            }
            $disponible = DisponibilidadItemData::disponibleParaCombinacion($souvenir, $talla, $sexo);
        } else {
            $disponible = $formType->cupoDisponible();
        }

        // null = disponibilidad no controlada (no debería pasar si
        // ListaEsperaController::store() validó bien al anotar, pero
        // por las dudas no se promueve nada sin un límite real) — 0 =
        // sigue lleno, tampoco hay nada que hacer.
        if (empty($disponible)) {
            return 0;
        }

        $pendientes = ListaEspera::where('form_types_id', $formTypeId)
            ->where('souvenir_id', $souvenirId)
            ->where('talla', $talla)
            ->where('sexo', $sexo)
            ->where('estado', 'pendiente')
            ->orderBy('created_at')
            ->limit($disponible)
            ->get();

        $promovidos = 0;
        foreach ($pendientes as $entry) {
            if ($this->promoverUno($formType, $entry)) {
                $promovidos++;
            }
        }

        return $promovidos;
    }

    private function promoverUno(FormType $formType, ListaEspera $entry): bool
    {
        $evento = Evento::find($entry->evento_id);
        if (!$evento) {
            return false;
        }

        try {
            Mail::to($entry->correo)->send(new ListaEsperaPromovidaMail($evento, $formType, $entry));
        } catch (\Throwable $e) {
            Log::error("No se pudo notificar promoción de lista de espera a {$entry->correo} (lista_espera id {$entry->id}): {$e->getMessage()}");

            return false;
        }

        $entry->update(['estado' => 'promovido', 'promovido_at' => now()]);

        return true;
    }
}

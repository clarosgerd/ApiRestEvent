<?php

namespace App\Services;

use App\Models\Mensaje;

/**
 * Canal WhatsApp "externo" (brain/PLAN-NOTIFICACIONES.md §2.4): no envía
 * nada, solo inserta un registro en la tabla `mensaje` — un software externo
 * (no OpenWA) la lee y hace el envío real.
 */
class WhatsappExternoService
{
    public function encolar(?string $celularCrudo, string $mensaje, string $tipo, ?string $email = null, int $prioridad = 5): void
    {
        $celular = $this->normalizarCelular($celularCrudo);

        if ($celular === null) {
            return;
        }

        Mensaje::create([
            'celular'   => $celular,
            'mensaje'   => mb_substr($mensaje, 0, 500),
            'email'     => $email,
            'tipo'      => $tipo,
            'prioridad' => $prioridad,
        ]);
    }

    /**
     * `celular` es unsignedBigInteger — se descarta todo lo que no sea
     * dígito (+, espacios, guiones) para no fallar el insert.
     */
    private function normalizarCelular(?string $crudo): ?int
    {
        if (!$crudo) {
            return null;
        }

        $digitos = preg_replace('/\D+/', '', $crudo);

        return $digitos !== '' ? (int) $digitos : null;
    }
}

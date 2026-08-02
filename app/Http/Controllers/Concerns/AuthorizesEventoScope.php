<?php

namespace App\Http\Controllers\Concerns;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Usado por EventoController y los controladores sueltos de
 * Category/FormType/PromoCode/Coordinate/Route para reforzar el scoping de
 * rol `admin` (un único evento asignado) — ver
 * brain/PLAN-PANEL-ADMIN-EVENTOS-02082026.md §1.2.
 */
trait AuthorizesEventoScope
{
    protected function assertCanWriteEvento(int $eventoId): void
    {
        $admin = auth('admins')->user();

        if ($admin && $admin->rol === 'admin' && $admin->evento_id !== $eventoId) {
            throw new HttpException(403, 'No tiene acceso a este evento.');
        }
    }

    protected function assertIsSuperAdmin(): void
    {
        $admin = auth('admins')->user();

        if (!$admin || $admin->rol !== 'super_admin') {
            throw new HttpException(403, 'Esta acción requiere rol super_admin.');
        }
    }
}

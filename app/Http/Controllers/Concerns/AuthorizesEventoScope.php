<?php

namespace App\Http\Controllers\Concerns;

use App\Models\AdminUser;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Usado por EventoController y los controladores sueltos de
 * Category/FormType/PromoCode/Coordinate/Route para reforzar el scoping de
 * rol `admin` (un único evento asignado) — ver
 * brain/PLAN-PANEL-ADMIN-EVENTOS-02082026.md §1.2.
 */
trait AuthorizesEventoScope
{
    /**
     * Caja de cobro presencial (14/08/2026) — ver
     * PLAN-CAJA-COBRO-PRESENCIAL-14082026.md. Antes de agregar el rol
     * `cajero`, esta guarda solo bloqueaba explícitamente el caso
     * `rol === 'admin'` con evento distinto — cualquier OTRO rol (incluido
     * uno nuevo como `cajero`) pasaba de largo sin chequeo alguno, porque
     * la condición nunca se activaba para él. Hoy con 3 roles posibles
     * hace falta una lista blanca explícita: solo `admin`/`super_admin`
     * pueden operar las pantallas protegidas por este método — `cajero`
     * queda fuera de todo lo que no sea el propio módulo de Caja (ver
     * assertCanOperarCaja() más abajo).
     */
    protected function assertCanWriteEvento(int $eventoId): void
    {
        $admin = auth('admins')->user();

        if (!$admin || !in_array($admin->rol, ['admin', 'super_admin'], true)) {
            throw new HttpException(403, 'No tiene acceso a este evento.');
        }

        if ($admin->rol === 'admin' && (int) $admin->evento_id !== $eventoId) {
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

    /**
     * Guarda de los endpoints de Caja — a diferencia de
     * assertCanWriteEvento(), acá SÍ se admite el rol `cajero` (además de
     * `admin`/`super_admin`), siempre scoped a su propio evento_id (salvo
     * super_admin, que opera cualquier evento). Devuelve el AdminUser
     * autenticado para que el controller no tenga que volver a resolverlo.
     */
    protected function assertCanOperarCaja(int $eventoId): AdminUser
    {
        $admin = auth('admins')->user();

        if (!$admin || !in_array($admin->rol, ['admin', 'cajero', 'super_admin'], true)) {
            throw new HttpException(403, 'No tiene acceso a la caja de este evento.');
        }

        if (in_array($admin->rol, ['admin', 'cajero'], true) && (int) $admin->evento_id !== $eventoId) {
            throw new HttpException(403, 'No tiene acceso a la caja de este evento.');
        }

        return $admin;
    }
}

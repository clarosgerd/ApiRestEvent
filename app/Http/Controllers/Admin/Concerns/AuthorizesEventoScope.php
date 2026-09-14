<?php

namespace App\Http\Controllers\Admin\Concerns;

/**
 * Admin de evento asignado a varios eventos (28/08/2026, sincronizado al
 * monolito 14/09/2026) — ver admin-eventos commit c8482e8 y
 * ApiRestEvent/brain/api_rest_event/PLAN-ADMIN-MULTI-EVENTO-28082026.md.
 *
 * Consolida acá los 13 `assertCanViewEvento()` que hasta esta fecha estaban
 * duplicados idénticos (uno privado por controller). `session('admin_user')`
 * ya trae `eventoIds` (evento principal + eventos adicionales) sin ningún
 * cambio de código en este módulo — viene directo de
 * `AdminAuthController::login()` (API real, llamada in-process desde
 * `Admin\AuthController::login()`), que ya expone ese campo desde que se
 * cherry-pickeó `94b9d40` del lado ApiRestEvent.
 */
trait AuthorizesEventoScope
{
    protected function assertCanViewEvento(int $evento): void
    {
        $admin = session('admin_user');

        if (($admin['rol'] ?? null) !== 'super_admin' && !in_array($evento, $admin['eventoIds'] ?? [], true)) {
            abort(403, 'No tiene acceso a este evento.');
        }
    }
}

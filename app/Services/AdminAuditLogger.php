<?php

namespace App\Services;

use App\Models\AdminAuditLog;
use App\Models\AdminUser;
use App\Models\Persona;

/**
 * Llamado explícitamente desde cada store/update/destroy/publicar de los 6
 * controladores de administración — no vía Eloquent Observers, porque
 * EventoService crea categorías/form_types con Model::insert() (bulk), que
 * no dispara eventos Eloquent y un Observer se perdería esos casos. Ver
 * brain/PLAN-PANEL-ADMIN-EVENTOS-02082026.md §1.3.
 */
class AdminAuditLogger
{
    /**
     * `$actor` opcional (02/10/2026, app de staff offline) — default `null`
     * preserva el comportamiento de siempre (`auth('admins')->user()`,
     * cero cambios en los call-sites existentes). Un check-in hecho desde
     * la app de staff pasa una `Persona` explícita, porque ahí no hay
     * sesión de `admins` de la que tirar.
     */
    public static function log(
        string $accion,
        string $entidad,
        int $entidadId,
        ?int $eventoId,
        ?array $before,
        ?array $after,
        AdminUser|Persona|null $actor = null,
    ): void {
        $actor ??= auth('admins')->user();

        AdminAuditLog::create([
            'admin_user_id' => $actor instanceof AdminUser ? $actor->id : null,
            'persona_id'    => $actor instanceof Persona ? $actor->id : null,
            'accion'        => $accion,
            'entidad'       => $entidad,
            'entidad_id'    => $entidadId,
            'evento_id'     => $eventoId,
            'datos_antes'   => $before,
            'datos_despues' => $after,
        ]);
    }
}

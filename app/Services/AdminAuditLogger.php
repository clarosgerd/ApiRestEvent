<?php

namespace App\Services;

use App\Models\AdminAuditLog;

/**
 * Llamado explícitamente desde cada store/update/destroy/publicar de los 6
 * controladores de administración — no vía Eloquent Observers, porque
 * EventoService crea categorías/form_types con Model::insert() (bulk), que
 * no dispara eventos Eloquent y un Observer se perdería esos casos. Ver
 * brain/PLAN-PANEL-ADMIN-EVENTOS-02082026.md §1.3.
 */
class AdminAuditLogger
{
    public static function log(
        string $accion,
        string $entidad,
        int $entidadId,
        ?int $eventoId,
        ?array $before,
        ?array $after
    ): void {
        AdminAuditLog::create([
            'admin_user_id' => auth('admins')->id(),
            'accion'        => $accion,
            'entidad'       => $entidad,
            'entidad_id'    => $entidadId,
            'evento_id'     => $eventoId,
            'datos_antes'   => $before,
            'datos_despues' => $after,
        ]);
    }
}

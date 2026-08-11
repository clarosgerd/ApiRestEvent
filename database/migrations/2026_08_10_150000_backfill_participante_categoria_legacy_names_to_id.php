<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Bug real encontrado en QA: el filtro por categoría de
 * Numeración/Participantes (admin-eventos) solo funcionaba con
 * "Todas las categorías" — nunca con una categoría específica.
 *
 * Causa: `participantes.categoria` tiene dos representaciones distintas
 * según el camino de creación. El registro online (elascenso/event,
 * `CrearInscripcionAction`) guarda el **ID** de la categoría
 * (`catEl.dataset.id` del lado del cliente). La carga masiva por CSV del
 * panel (`RegistrationController::importarBulk`) guardaba el **nombre**
 * (`$category->name`) — se corrigió en el mismo commit que esta migración
 * para que guarde el ID también de acá en adelante. El filtro del panel
 * compara por ID (`ParticipanteController::porEvento` hace
 * `where('categoria', $categoria)` con el valor que manda el `<select>`,
 * también corregido para mandar ID) — con las dos representaciones
 * mezcladas, cualquier evento con participantes de ambos orígenes solo
 * "funcionaba" para el origen minoritario.
 *
 * Esta migración normaliza los datos **ya existentes**: cualquier
 * `participantes.categoria` que no sea puramente numérico (es decir, que
 * quedó guardado como nombre por el bug de arriba) se reemplaza por el ID
 * de la categoría del evento correspondiente cuyo nombre coincide
 * (case-insensitive). Los que ya son numéricos (la mayoría — registro
 * online) no se tocan.
 *
 * Probada antes contra `event_testing` — no corrida contra la BD real
 * sin confirmar el impacto primero (ver
 * feedback_dry_run_antes_de_correr_contra_bd_real en memoria).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            UPDATE participantes p
            INNER JOIN registrations r ON r.id = p.registration_id
            INNER JOIN categories c ON c.event_id = r.evento_id
                AND LOWER(TRIM(c.name)) = LOWER(TRIM(p.categoria))
            SET p.categoria = c.id
            WHERE p.categoria NOT REGEXP '^[0-9]+$'
        ");
    }

    public function down(): void
    {
        // Irreversible a propósito: no hay forma de recuperar el nombre
        // original una vez reemplazado por el ID (la conversión no guarda
        // el valor previo). Si hace falta revertir, restaurar desde backup.
    }
};

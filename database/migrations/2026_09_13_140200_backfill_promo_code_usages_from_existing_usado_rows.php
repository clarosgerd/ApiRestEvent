<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Promo code multi-uso (13/09/2026) — backfill de datos, no de schema.
 * Decisión confirmada con el usuario: migrar el historial de los ~150
 * códigos ya `usado=true` a `promo_code_usages`, para que el reporte
 * reescrito (una fila por USO, no por código) no pierda detalle de
 * quién/cuándo para lo que ya pasó.
 *
 * Matchea por `registration_id` (el FK que `promo_codes` ya guardaba de
 * quién lo consumió), no solo por el string del código — más preciso que
 * el `keyBy('promo_codigo')` que usa hoy PromoCodeReporteData::paraEvento()
 * (que solo matchea por texto), y evita falsos positivos si dos eventos
 * distintos tuvieran participantes con el mismo texto de código por
 * coincidencia.
 *
 * Idempotente (no inserta si la tabla ya tiene filas) — seguro de correr
 * más de una vez por error. No toca `promo_codes` ni `participantes`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('promo_code_usages')->exists()) {
            return;
        }

        DB::statement('
            INSERT INTO promo_code_usages (promo_code_id, registration_id, participante_id, monto_descontado, used_at)
            SELECT pc.id, pc.registration_id, p.id, p.promo_descuento, p.created_at
            FROM promo_codes pc
            INNER JOIN participantes p
                ON p.registration_id = pc.registration_id
               AND BINARY p.promo_codigo = pc.promo_code
            WHERE pc.usado = 1
              AND pc.registration_id IS NOT NULL
        ');
    }

    public function down(): void
    {
        DB::table('promo_code_usages')->truncate();
    }
};

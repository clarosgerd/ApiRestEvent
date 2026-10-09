<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * WhatsApp Business API oficial por organizador (08/10/2026) — pedido real
 * del usuario: cada organizador que acepte el servicio en su contrato puede
 * registrar sus propias credenciales de la API oficial de Meta (ver
 * whatsapp_cuentas) y usarlas para mandar confirmación de pago y
 * recordatorios, además de (no en vez de) el correo, que sigue siendo
 * obligatorio sin cambios.
 *
 * `whatsapp_canal` es un ENUM real en MySQL — modificar uno existente
 * necesita `ALTER TABLE ... MODIFY` (`Schema::table()->enum()` no sirve
 * para esto), mismo patrón ya usado en `caja_movimientos.tipo` (02/10/2026).
 * Nace en 'ninguno' para todos (default sin cambios) — activar 'oficial'
 * para un organizador es una decisión manual de un super_admin, nunca
 * automática.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE organizadores MODIFY whatsapp_canal ENUM('ninguno','openwa','externo','oficial') NOT NULL DEFAULT 'ninguno'");
    }

    public function down(): void
    {
        DB::statement("UPDATE organizadores SET whatsapp_canal = 'ninguno' WHERE whatsapp_canal = 'oficial'");
        DB::statement("ALTER TABLE organizadores MODIFY whatsapp_canal ENUM('ninguno','openwa','externo') NOT NULL DEFAULT 'ninguno'");
    }
};

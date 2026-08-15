<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Caja de cobro presencial (14/08/2026) — ver
 * PLAN-CAJA-COBRO-PRESENCIAL-14082026.md. Amplía el enum de rol para
 * agregar 'cajero' — scoped a un evento igual que 'admin', pero con
 * permisos mínimos (solo el módulo de Caja, reforzado en
 * AuthorizesEventoScope).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE admin_users MODIFY COLUMN rol ENUM('super_admin', 'admin', 'cajero') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE admin_users MODIFY COLUMN rol ENUM('super_admin', 'admin') NOT NULL");
    }
};

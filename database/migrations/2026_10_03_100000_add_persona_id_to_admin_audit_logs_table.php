<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * App de staff offline (02/10/2026) — un check-in hecho desde la app de
 * staff (guard `sanctum`/`Persona`, no `AdminUser`) dejaba `admin_user_id`
 * siempre en null en `admin_audit_logs` (AdminAuditLogger::log()
 * hardcodeaba `auth('admins')->id()`), sin trazabilidad real de qué
 * miembro del staff hizo cada check-in offline. Columna aditiva, nullable
 * — ningún registro existente cambia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_audit_logs', function (Blueprint $table) {
            $table->foreignId('persona_id')->nullable()->after('admin_user_id')
                ->constrained('personas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('admin_audit_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('persona_id');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Check-in por sesión desde la app de staff (05/10/2026). La asistencia solo
 * guardaba `staff_admin_user_id` (AdminUser); un check-in hecho por una Persona
 * de la app quedaba sin autor. Columna aditiva, nullable: ningún registro cambia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asistencia_sesion', function (Blueprint $table) {
            $table->foreignId('staff_persona_id')->nullable()->after('staff_admin_user_id')
                ->constrained('personas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('asistencia_sesion', function (Blueprint $table) {
            $table->dropConstrainedForeignId('staff_persona_id');
        });
    }
};

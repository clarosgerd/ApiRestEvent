<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ver brain/PLAN-ASIGNACION-STAFF-SESIONES-CONGRESO-13082026.md. Flag
 * explícito para no depender de comparar `form_types.tipo`/`name` contra
 * strings como "Staff"/"Ayudante"/"Voluntario" (frágil — mismo criterio
 * que has_team/has_delivery/has_donation, boolean explícito en vez de
 * inferir del nombre). Solo los participantes inscritos bajo un form_type
 * con este flag son asignables a sesiones de congreso.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_types', function (Blueprint $table) {
            $table->boolean('es_staff')->default(false)->after('has_promo_code');
        });
    }

    public function down(): void
    {
        Schema::table('form_types', function (Blueprint $table) {
            $table->dropColumn('es_staff');
        });
    }
};

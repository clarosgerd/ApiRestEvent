<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Caja para eventos tipo congreso (20/08/2026) — el contacto de
 * emergencia era obligatorio en TODOS los canales (formulario público y
 * Caja) sin importar el tipo de evento; tenía sentido para
 * maratones/carreras pero es ruido para un congreso donde ya no aplica.
 * Flag explícito por form_type (mismo criterio que has_team/has_delivery/
 * has_donation) en vez de inferir por `tipo === 'congreso'` — el
 * organizador decide, no una convención de nombre. Default true: ningún
 * evento existente cambia de comportamiento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_types', function (Blueprint $table) {
            $table->boolean('requiere_contacto_emergencia')->default(true)->after('es_ponente');
        });
    }

    public function down(): void
    {
        Schema::table('form_types', function (Blueprint $table) {
            $table->dropColumn('requiere_contacto_emergencia');
        });
    }
};

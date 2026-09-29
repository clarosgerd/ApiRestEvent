<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Identificar talleres precongreso y su formato (28/09/2026) — pedido del
 * usuario para CIACRUZ 2026 ("ALTO – Analgesia Multimodal", hoy solo texto
 * libre dentro del nombre, ej. "100% virtual · Previo al Congreso"). No se
 * confunde con `modalidad` (REQUIRED/OPTIONAL, significado distinto: si el
 * taller es obligatorio u opcional dentro del congreso). Aditiva pura:
 * `es_precongreso` default false, `formato` nullable — ningún taller
 * existente cambia de comportamiento. Fase 1 de
 * brain/PLAN-REGISTRO-EFICIENTE-TALLER-PRECONGRESO-28092026.md — Fase 2
 * (que el sitio público lo consuma) queda guardada para más adelante.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('talleres', function (Blueprint $table) {
            $table->boolean('es_precongreso')->default(false)->after('permite_inscripcion');
            // VIRTUAL | PRESENCIAL | HIBRIDO — nullable a propósito, no se
            // fuerza un valor que el organizador no cargó todavía.
            $table->string('formato', 20)->nullable()->after('es_precongreso');
        });
    }

    public function down(): void
    {
        Schema::table('talleres', function (Blueprint $table) {
            $table->dropColumn(['es_precongreso', 'formato']);
        });
    }
};

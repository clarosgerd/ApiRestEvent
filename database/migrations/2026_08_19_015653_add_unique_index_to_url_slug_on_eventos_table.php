<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link directo al evento vía url_slug (18/08/2026) — ver elascenso/event,
 * Evento::resolveRouteBinding(). `url_slug` ya se auto-generaba desde
 * CrearEventoAction (09/08/2026, `Str::slug($nombre).'-'.Str::random(6)`,
 * garantiza unicidad de hecho) pero sin índice único en BD; ahora que
 * admin-eventos deja al organizador editarlo a mano, un índice único
 * evita que dos eventos terminen resolviendo al mismo slug. Verificado
 * antes de correr: 61/61 eventos existentes ya tienen valor no vacío y
 * sin duplicados.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->unique('url_slug', 'eventos_url_slug_unique');
        });
    }

    public function down(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->dropUnique('eventos_url_slug_unique');
        });
    }
};

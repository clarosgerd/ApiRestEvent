<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sync pull con id externo estable (23/09/2026) — evita el duplicado que
 * generaba SincronizarParticipanteExternoAction cuando la fuente corregía
 * el numero_documento de alguien ya sincronizado (la búsqueda de "ya
 * existe" era justamente por numero_documento). Guarda algo como
 * "pull:3:EXT-4821" (config id + id propio que mande la fuente, opcional).
 * `unique` con NULL permitido (COLABIOCLI, que nunca manda external_id,
 * sigue con el flujo de siempre) — ver App\Actions\SincronizarParticipanteExternoAction.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->string('origen_sync_externo')->nullable()->unique()->after('origen_legado');
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropColumn('origen_sync_externo');
        });
    }
};

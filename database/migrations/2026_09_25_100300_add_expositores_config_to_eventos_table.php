<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SmartStand (25/09/2026) — configuración de expositores por evento
 * (`app_url_ios`, `app_url_android`, `instrucciones`, `dashboard_url`), mismo
 * patrón que `gafete_config`: JSON nullable, NULL = sin configurar.
 * No es sensible (links de tiendas de apps) — EventoResource es público.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->json('expositores_config')->nullable()->after('gafete_config');
        });
    }

    public function down(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->dropColumn('expositores_config');
        });
    }
};

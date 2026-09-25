<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SmartStand (25/09/2026) — mismo criterio que `es_staff`/`es_ponente`: flag
 * explícito en vez de inferir del nombre/tipo del form_type. Una inscripción
 * bajo un form_type con este flag es la de una EMPRESA EXPOSITORA: al
 * confirmarse el pago se le crea la cuenta de expositor (ver
 * ProvisionarCuentaExpositorAction). Aditiva, default false.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_types', function (Blueprint $table) {
            $table->boolean('es_expositor')->default(false)->after('es_ponente');
        });
    }

    public function down(): void
    {
        Schema::table('form_types', function (Blueprint $table) {
            $table->dropColumn('es_expositor');
        });
    }
};

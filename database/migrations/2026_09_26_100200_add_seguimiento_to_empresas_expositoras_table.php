<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SmartStand fase 4 (26/09/2026) — ajustes del correo de seguimiento de cada
 * empresa. Apagado por defecto; además tiene que estar habilitado en el evento
 * (eventos.expositores_config.seguimiento_habilitado). Aditiva.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas_expositoras', function (Blueprint $table) {
            $table->boolean('seguimiento_activo')->default(false)->after('activo');
            $table->string('seguimiento_asunto', 150)->nullable()->after('seguimiento_activo');
            $table->text('seguimiento_mensaje')->nullable()->after('seguimiento_asunto');
            $table->string('seguimiento_url', 500)->nullable()->after('seguimiento_mensaje');
            $table->string('seguimiento_reply_to')->nullable()->after('seguimiento_url');
        });
    }

    public function down(): void
    {
        Schema::table('empresas_expositoras', function (Blueprint $table) {
            $table->dropColumn(['seguimiento_activo', 'seguimiento_asunto', 'seguimiento_mensaje', 'seguimiento_url', 'seguimiento_reply_to']);
        });
    }
};

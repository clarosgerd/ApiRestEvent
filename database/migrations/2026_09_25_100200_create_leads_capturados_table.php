<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SmartStand (25/09/2026) — un lead = una empresa expositora escaneó el
 * gafete de un participante. El UNIQUE (empresa, participante) hace que
 * re-escanear el mismo gafete actualice nota/calificación en vez de duplicar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads_capturados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_expositora_id')->constrained('empresas_expositoras')->cascadeOnDelete();
            $table->foreignId('participante_id')->constrained('participantes')->cascadeOnDelete();
            $table->text('nota')->nullable();
            $table->unsignedTinyInteger('calificacion')->nullable();
            // dateTime y NO timestamp: en MariaDB/MySQL la primera columna
            // TIMESTAMP NOT NULL de una tabla recibe implícitamente
            // `ON UPDATE CURRENT_TIMESTAMP`, y cada edición de nota/calificación
            // reescribía la fecha del primer escaneo (detectado en test).
            $table->dateTime('capturado_at');
            $table->timestamps();

            $table->unique(['empresa_expositora_id', 'participante_id'], 'leads_empresa_participante_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads_capturados');
    }
};

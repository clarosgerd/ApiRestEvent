<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sync periódico (pull) de participantes de un evento con registro propio
 * externo (17/09/2026) — ver plan en la memoria del proyecto
 * (project_sync_pull_evento_fuente_externa) y App\Actions\
 * SincronizarParticipanteExternoAction / App\Services\SyncExternoPullService.
 *
 * Distinto del webhook de COLABIOCLI (push, sin config persistida — el
 * secreto es global vía EXTERNAL_SYNC_SECRET): acá SOMOS nosotros quienes
 * llamamos a la fuente, así que hace falta guardar por evento a qué URL
 * llamar y con qué token, una fila = una fuente activa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eventos_sync_externo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evento_id')->unique()->constrained('eventos')->cascadeOnDelete();
            // Default/fallback — el JSON puede traer `form_type` por
            // participante para eventos híbridos (ver SyncExternoPullService).
            $table->foreignId('form_types_id')->constrained('form_types')->cascadeOnDelete();
            $table->string('nombre_fuente')->nullable();
            $table->text('url');
            $table->string('token')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamp('ultima_sincronizacion_at')->nullable();
            $table->json('ultimo_resultado')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos_sync_externo');
    }
};

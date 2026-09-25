<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SmartStand fase 4 (26/09/2026) — historial de sorteos, una sola tabla para
 * los dos tipos: `expositor` (una empresa sortea entre sus propios contactos)
 * y `pasaporte` (el organizador sortea entre quienes visitaron >= N stands).
 *
 * `ganador` es un snapshot (nombre, correo, teléfono, ciudad): si el
 * participante cambia o se borra después, el sorteo sigue siendo demostrable.
 * `candidatos_hash` = sha256 de los ids candidatos ordenados: permite
 * demostrar sobre qué lista se sorteó. `sorteado_at` es DATETIME a propósito
 * (un TIMESTAMP NOT NULL recibe ON UPDATE CURRENT_TIMESTAMP implícito en MariaDB).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sorteos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evento_id')->constrained('eventos')->cascadeOnDelete();
            $table->string('tipo', 20);
            $table->foreignId('empresa_expositora_id')->nullable()->constrained('empresas_expositoras')->nullOnDelete();
            $table->string('premio', 150);
            $table->foreignId('participante_ganador_id')->nullable()->constrained('participantes')->nullOnDelete();
            $table->json('ganador');
            $table->unsignedInteger('candidatos_count');
            $table->char('candidatos_hash', 64);
            $table->json('filtros')->nullable();
            $table->unsignedBigInteger('admin_user_id')->nullable();
            $table->dateTime('sorteado_at');
            $table->timestamps();

            $table->index(['evento_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sorteos');
    }
};

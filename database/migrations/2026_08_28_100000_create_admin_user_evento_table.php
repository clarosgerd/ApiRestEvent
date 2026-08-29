<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin de evento asignado a varios eventos (28/08/2026) — ver
 * brain/api_rest_event/PLAN-ADMIN-MULTI-EVENTO-28082026.md.
 *
 * `admin_users.evento_id` sigue siendo el "evento principal" del admin,
 * exactamente como hoy — esta tabla solo agrega EVENTOS ADICIONALES,
 * 100% opt-in. Aditiva pura, sin backfill: un admin sin filas acá se
 * comporta idéntico a antes de esta migración.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_user_evento', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_user_id');
            $table->unsignedBigInteger('evento_id');
            $table->timestamps();

            $table->foreign('admin_user_id')->references('id')->on('admin_users')->cascadeOnDelete();
            $table->foreign('evento_id')->references('id')->on('eventos')->cascadeOnDelete();
            $table->unique(['admin_user_id', 'evento_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_user_evento');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Igual patrón que `registration_notifications`, pero para avisos que son
     * por evento (no por inscripción individual) — ej. el aviso al
     * organizador de "tu evento empieza en 15 días" con el link del
     * dashboard, que se manda una sola vez por evento.
     */
    public function up(): void
    {
        Schema::create('evento_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evento_id')->constrained('eventos')->cascadeOnDelete();
            $table->string('tipo');
            $table->string('canal');
            $table->timestamp('enviado_at');
            $table->unique(['evento_id', 'tipo', 'canal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evento_notifications');
    }
};

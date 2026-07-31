<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resultados', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            // Nullable: si el chip/bib/documento del bulk no matchea a
            // ningún participante inscrito, el resultado se guarda igual
            // (corredor "bandit" o error de numeración) — ver §2 del plan.
            $table->unsignedBigInteger('participante_id')->nullable();

            // Se guardan tal cual vinieron en el bulk, además del FK
            // resuelto, para poder auditar/reprocesar si el match falló.
            $table->string('chip')->nullable();
            $table->string('numero_corredor')->nullable();
            $table->string('numero_documento')->nullable();

            $table->string('tiempo_oficial')->nullable();
            $table->string('tiempo_chip')->nullable();
            $table->unsignedInteger('posicion_general')->nullable();
            $table->unsignedInteger('posicion_categoria')->nullable();
            $table->unsignedInteger('posicion_genero')->nullable();
            $table->string('estado')->default('finisher'); // finisher|dns|dnf|dsq
            $table->timestamps();

            $table->index('event_id');
            $table->index('participante_id');
            $table->index('chip');
            $table->index('numero_corredor');
            $table->index('numero_documento');
        });

        Schema::table('resultados', function (Blueprint $table) {
            $table->foreign('event_id')->references('id')->on('eventos')->cascadeOnDelete();
            $table->foreign('participante_id')->references('id')->on('participantes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resultados');
    }
};

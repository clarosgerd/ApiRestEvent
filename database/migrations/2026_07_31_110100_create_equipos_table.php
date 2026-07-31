<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->string('nombre');
            $table->timestamps();

            $table->index('event_id');
            $table->unique(['event_id', 'nombre']);
        });

        Schema::table('equipos', function (Blueprint $table) {
            $table->foreign('event_id')->references('id')->on('eventos')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipos');
    }
};

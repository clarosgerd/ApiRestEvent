<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ciudades', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('pais_id');
            $table->string('nombre', 100);
            $table->boolean('activo')->default(true);

            $table->foreign('pais_id')->references('id')->on('paises');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ciudades');
    }
};

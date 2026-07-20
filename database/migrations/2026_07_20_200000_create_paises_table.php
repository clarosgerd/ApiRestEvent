<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paises', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('nombre', 100);
            $table->char('iso2', 2);
            $table->char('iso3', 3)->nullable();
            $table->string('prefijo_tel', 6)->nullable();
            $table->string('bandera_url', 255)->nullable();
            $table->boolean('activo')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paises');
    }
};

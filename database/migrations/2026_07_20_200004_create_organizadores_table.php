<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizadores', function (Blueprint $table) {
            $table->increments('id');
            $table->string('razon_social', 150);
            $table->string('nombre_comercial', 100)->nullable();
            $table->string('rut_nit', 30)->nullable();
            $table->string('email', 150);
            $table->string('telefono', 30)->nullable();
            $table->unsignedSmallInteger('pais_id')->nullable();
            $table->unsignedInteger('ciudad_id')->nullable();
            $table->string('direccion', 255)->nullable();
            $table->string('logo_url', 255)->nullable();
            $table->unsignedTinyInteger('plan_id')->default(1);
            $table->decimal('comision_especial', 5, 2)->nullable();
            $table->text('convenio_notas')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->foreign('pais_id')->references('id')->on('paises');
            $table->foreign('ciudad_id')->references('id')->on('ciudades');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizadores');
    }
};

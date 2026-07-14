<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->unsignedBigInteger('event_id');
            $table->integer('formulario_id')->nullable();;
            $table->integer('sexo_id')->nullable();;
            $table->string('color', 255)->nullable();;
            $table->string('name', 255);
            $table->decimal('price', 10, 2);
            $table->text('description')->nullable();
            $table->integer('edad_min')->nullable();
            $table->integer('edad_max')->nullable();
            $table->integer('calculo_edad_id')->nullable();
        });
         Schema::table('categories', function ($table) {
            $table->foreign('event_id')->references('id')->on('eventos');
           // $table->foreign('formulario_id')->references('id')->on('formularios');
        });


    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categorias');
    }
};

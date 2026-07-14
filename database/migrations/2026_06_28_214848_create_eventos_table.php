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
        Schema::create('eventos', function (Blueprint $table) {
            $table->id();
            $table->integer('organizador_id')->unsigned();
            $table->integer('tipo_evento_id')->unsigned();
            $table->integer('subtipo_evento_id')->unsigned();
            $table->string('estado_evento_id');
            $table->integer('pais_id')->unsigned();
            $table->integer('ciudad_id')->unsigned();
            $table->String('nombre', 255);
            $table->String('nombre_corto', 255);
            $table->String('url_slug', 255);
            $table->String('keyword', 255);
            $table->String('descripcion', 500);
            $table->String('longDescription', 500);
            $table->String('reglamento', 500);
            $table->String('deslinde', 500);
            $table->dateTime('fecha_inicio');
            $table->dateTime('fecha_fin');
            $table->dateTime('fecha_apertura_inscrip');
            $table->dateTime('fecha_cierre_inscrip');
            $table->String('mensaje_cierre', 500);
            $table->String('lugar', 500);
            $table->String('direccion', 500);
            $table->enum('modalidad', ['presencial','virtual','hibrido'])->default('presencial'); // Define the ENUM column; 
            $table->String('url_virtual', 255);
            $table->integer('aforo_total')->unsigned();
            $table->integer('color_id')->unsigned();
            $table->String('logo_url', 255);
            $table->String('imagen_portada_url', 255);
            $table->String('icono_url', 255);
            $table->String('video_url', 255);
            $table->String('gpx_url', 255);
        //    $table->float('coordinates', 10, 6);  // Generates random float between -90 and 90
        //    $table->float('route', 10, 6); // Generates random float between -180 and 180
            $table->String('link_strava', 255);
            $table->enum('checkin_tipo', ['kit', 'acreditacion', 'ambos'])->default('kit'); // Define the ENUM column; String('checkin_tipo', 255);
            $table->boolean('tiene_delivery')->default(false);
            $table->boolean('tiene_punto_venta')->default(false);
            $table->boolean('tiene_desafios')->default(false);
            $table->boolean('publicado')->default(false);
            $table->boolean('destacado')->default(false);
            $table->boolean('hasDonation')->default(false);
            $table->integer('contador_visitas')->unsigned()->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('eventos');
    }
};

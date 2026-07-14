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
        Schema::create('form_types', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->unsignedBigInteger('event_id'); // Para guardar "challenge"
            $table->string('name');                   // Para "Challenge Series"
            $table->string('icon');                   // Guarda el emoji "🎯"
            $table->text('description');              // Para la descripción larga
            $table->enum('tipo', ['deportivo',
                'congreso',
                'taller',
                'corporativo',
                'cultural',
                'social',
                'educativo',
                'recreativo',
                'religioso',
                'gastronomico',
                'musical',
                'tecnologico',
                'artes',
                'literario',
                'ambiental',
                'salud',
                'moda',
                'teatro',
                'cine',
                'fotografia',
                'danza',
                'literatura'])->default('deportivo'); // Define the ENUM column;  
                $table->unsignedBigInteger('cupo_total');
                $table->decimal('precio_base', 10, 2);
                $table->boolean('activo')->default(1);   ;
                $table->boolean('moneda', 1)->default(1);
                $table->boolean('permite_lista_espera', 1)->default(1);
                $table->boolean('requiere_categoria', 1)->default(1);
                $table->boolean('requiere_talla', 1)->default(1);
                $table->boolean('requiere_distancia', 1)->default(1);
                $table->boolean('requiere_fecha_nacim', 1)->default(1);
                $table->boolean('permite_inscripcion_grupal', 1)->default(1);
                $table->boolean('max_integrantes_grupo', 1)->default(1);
                $table->boolean ('permite_inscripcion_tercero',1)->default(1);
                $table->decimal('costo_edicion', 10, 2);
                $table->unsignedBigInteger('tiempo_expiracion_min');
                $table->string('texto_boton', 60)->default('Inscribirme');
                $table->string('color', 7);               // Guarda el código HEX "#e67e22"
            $table->timestamps();
        });

         Schema::table('form_types', function ($table) {
            $table->foreign('event_id')->references('id')->on('eventos');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_types');
    }
};

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
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_types_id')
                ->constrained('form_types')
                ->onDelete('cascade');
		   $table->enum('seccion', ['personal','kit','encuesta','legal','otro'])->default('kit'); // Define the ENUM column; String('checkin_tipo', 255);
           $table->text('nombre_campo');
           $table->string('etiqueta');
           $table->enum('tipo_input', ['text','email','tel','date','number','select','checkbox','radio','textarea','file'])->default('text'); // Define the ENUM column; String('checkin_tipo', 255);
           $table->json('opciones');
           $table->string('placeholder');
           $table->boolean ('obligatorio',1)->default(1);
           $table->boolean ('visible_en_reporte',1)->default(1);
           $table->unsignedBigInteger('orden')->nullable();
           $table->timestamps();	
	 	   $table->index('form_types_id');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};

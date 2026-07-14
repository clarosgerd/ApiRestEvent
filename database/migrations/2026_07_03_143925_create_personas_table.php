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
        Schema::create('personas', function (Blueprint $table) {
          $table->id();
            $table->String('email', 255)->unique();;
            $table->String('password');
            $table->String('nombre', 255);
            $table->String('apellido', 255);
            $table->String('alias', 500);
            $table->String('sexo', 500);
        //    $table->String('tipo_documento', 500);
            $table->enum('tipo_documento', ['DNI', 'CI', 'Pasaporte'])->default('CI'); // Define the ENUM column; 
            $table->String('numero_documento', 500);
            $table->dateTime('fecha_nacimiento');
            $table->String('correo');
            $table->String('direccion');
            $table->String('ciudad');
            $table->String('telefono');
            $table->String('celular');
            $table->string('token');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personas');
    }
};

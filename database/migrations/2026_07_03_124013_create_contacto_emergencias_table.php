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
        Schema::create('contactos_emergencia', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('persona_id');

            $table->String('nombre', 500);
            $table->String('telefono', 500);
        //    $table->String('tipo_documento', 500);
            $table->enum('relacion', ['FAT',
                'MOT',
                'BRO',
                'SIS',
                'WIF',
                'HUS',
                'SON',
                'DAU',
                'FRI'])->default('FAT'); // Define the ENUM column; 
            $table->String('email', 255)->unique();;
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contactos_emergencia');
    }
};

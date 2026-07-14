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
        
        Schema::create('souvenirs', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->unsignedBigInteger('event_id'); // Para guardar "challenge"
            $table->string('name');                   // Para "Challenge Series"
            $table->string('icon');                   // Guarda el emoji "🎯"
            $table->decimal('price', 10, 2);          // Para la descripción larga
            
        });

          Schema::table('souvenirs', function ($table) {
            $table->foreign('event_id')->references('id')->on('eventos');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('souvenirs');
    }
};

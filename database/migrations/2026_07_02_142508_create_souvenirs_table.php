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
            $table->id('id');
           /*     $table->foreignId('form_types_id')
                ->constrained()
                ->cascadeOnDelete();*/
            //$table->unsignedBigInteger('form_types_id'); // Para guardar "challenge"
            //$table->foreign('form_types_id')->references('id')->on('form_types')->cascadeOnDelete();
              $table->foreignId('form_types_id')
                ->constrained('form_types')
                ->onDelete('cascade');
            $table->string('name');                   // Para "Challenge Series"
            $table->string('icon');                   // Guarda el emoji "🎯"
            $table->decimal('price', 10, 2);          // Para la descripción larga
            $table->index('form_types_id');
            $table->timestamps();
        });

         /* Schema::table('souvenirs', function ($table) {
            $table->foreign('form_types_id')->references('id')->on('form_types');
        });*/
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('souvenirs');
    }
};

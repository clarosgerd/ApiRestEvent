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
        Schema::create('souvenir_participantes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('participante_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->unsignedBigInteger('souvenir_id');
            $table->string('nombre');
            $table->decimal('precio',10,2);
            $table->timestamps();
            $table->index('participante_id');
            $table->index('souvenir_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('souvenir_participantes');
    }
};

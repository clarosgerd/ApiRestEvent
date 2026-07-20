<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subtipos_evento', function (Blueprint $table) {
            $table->tinyIncrements('id');
            $table->unsignedTinyInteger('tipo_evento_id');
            $table->string('nombre', 80);
            $table->boolean('activo')->default(true);

            $table->foreign('tipo_evento_id')->references('id')->on('tipos_evento');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subtipos_evento');
    }
};

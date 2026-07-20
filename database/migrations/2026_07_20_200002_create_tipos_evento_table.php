<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_evento', function (Blueprint $table) {
            $table->tinyIncrements('id');
            $table->string('nombre', 80);
            $table->string('icono', 50)->nullable();
            $table->boolean('activo')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_evento');
    }
};

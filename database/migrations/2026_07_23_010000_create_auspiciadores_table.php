<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auspiciadores', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->unsignedBigInteger('event_id');
            $table->string('nombre', 255);
            $table->string('logo_url', 500);
            $table->string('contacto', 500)->nullable();
            $table->integer('orden')->nullable();
        });

        Schema::table('auspiciadores', function (Blueprint $table) {
            $table->foreign('event_id')->references('id')->on('eventos');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auspiciadores');
    }
};

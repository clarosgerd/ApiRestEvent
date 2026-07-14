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
         Schema::create('routes', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->unsignedBigInteger('event_id');
            $table->float('lat', 10, 6);  // Generates random float between -90 and 90
            $table->float('lng', 10, 6); // Generates random float between -180 and 180
            $table->string('label', 500);
        });
         Schema::table('routes', function ($table) {
            $table->foreign('event_id')->references('id')->on('eventos');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('routes');
    }
};

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
        Schema::table('participantes', function (Blueprint $table) {
            //
            $table->foreign('registration_id')->references('id')->on('registrations')->cascadeOnDelete();
      /*  $table->foreignId('registration_id')
                ->constrained()
                ->cascadeOnDelete();*/
        });

        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('participantes', function (Blueprint $table) {
            //
             $table->dropForeign(['registration_id']);
        });
    }
};

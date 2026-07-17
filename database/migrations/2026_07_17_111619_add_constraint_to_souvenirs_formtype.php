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
         Schema::table('souvenirs', function ($table) {
           // $table->foreign('form_types_id')->references('id')->on('form_types');
           // $table->foreign('form_types_id')->references('id')->on('form_types')->cascadeOnDelete();;
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('souvenirs', function (Blueprint $table) {
            //
            //$table->dropForeign(['form_types_id']);
        });
    }
};

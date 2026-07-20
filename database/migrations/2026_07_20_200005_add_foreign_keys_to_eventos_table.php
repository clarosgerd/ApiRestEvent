<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->foreign('organizador_id')->references('id')->on('organizadores');
            $table->foreign('tipo_evento_id')->references('id')->on('tipos_evento');
            $table->foreign('subtipo_evento_id')->references('id')->on('subtipos_evento');
            $table->foreign('pais_id')->references('id')->on('paises');
            $table->foreign('ciudad_id')->references('id')->on('ciudades');
        });
    }

    public function down(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->dropForeign(['organizador_id']);
            $table->dropForeign(['tipo_evento_id']);
            $table->dropForeign(['subtipo_evento_id']);
            $table->dropForeign(['pais_id']);
            $table->dropForeign(['ciudad_id']);
        });
    }
};

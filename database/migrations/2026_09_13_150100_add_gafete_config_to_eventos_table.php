<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Config de tamaño/layout del gafete: {width_cm, height_cm, per_row,
     * paper, orientation}. NULL = usa el default hardcodeado actual (7x5cm,
     * 3 por fila, A4 horizontal) — mismo criterio que
     * secciones_orden/campos_ocultos: aditivo, no toca ningún evento
     * existente.
     */
    public function up(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->json('gafete_config')->nullable()->after('color_hex');
        });
    }

    public function down(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->dropColumn('gafete_config');
        });
    }
};

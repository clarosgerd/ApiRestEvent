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
        Schema::table('eventos', function (Blueprint $table) {
            // Color de marca del evento (ej. '#022858'), usado en gafetes y
            // certificados — a diferencia de `color_id` (columna vieja, sin
            // tabla de colores detrás, siempre 1), este campo se guarda tal
            // cual lo manda el organizador.
            $table->string('color_hex', 7)->nullable()->after('color_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->dropColumn('color_hex');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // `categoria` se creó como unsignedBigInteger (pensado como FK a
        // categories.id), pero en realidad siempre se guarda el *nombre* de
        // la categoría ("5K", "General", "VIP"...) — ParticipantDTO le hacía
        // (int) a ese string, truncándolo silenciosamente ("5K" -> 5,
        // "General" -> 0). Se usa SQL crudo porque cambiar el tipo de una
        // columna existente con Schema::table()->change() requiere
        // doctrine/dbal, que no está instalado en este proyecto (mismo
        // criterio que 2026_07_20_182731_fix_max_integrantes_grupo...).
        DB::statement('ALTER TABLE participantes MODIFY categoria VARCHAR(255) NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE participantes MODIFY categoria BIGINT UNSIGNED NOT NULL');
    }
};

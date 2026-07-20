<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // max_integrantes_grupo se creó como boolean por error (debía ser la
        // cantidad máxima de integrantes del grupo, no un flag). Se usa SQL
        // crudo para el ALTER de tipo porque cambiar el tipo de una columna
        // existente con Schema::table()->change() requiere doctrine/dbal,
        // que no está instalado en este proyecto.
        DB::statement('ALTER TABLE form_types MODIFY max_integrantes_grupo INT UNSIGNED NOT NULL DEFAULT 10');

        // Normaliza los datos sembrados antes de este cambio: venían de la
        // columna boolean anterior, así que solo podían ser 0 o 1 — ninguno
        // de los dos tiene sentido como "máximo de integrantes".
        DB::table('form_types')->whereIn('max_integrantes_grupo', [0, 1])->update(['max_integrantes_grupo' => 10]);

        Schema::table('form_types', function (Blueprint $table) {
            $table->decimal('descuento_registrante_pct', 4, 2)->default(0.10)->after('max_integrantes_grupo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('form_types', function (Blueprint $table) {
            $table->dropColumn('descuento_registrante_pct');
        });

        DB::statement('ALTER TABLE form_types MODIFY max_integrantes_grupo TINYINT(1) NOT NULL DEFAULT 1');
    }
};

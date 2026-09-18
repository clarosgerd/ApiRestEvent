<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aviso de numeración vs. género/edad real en entrega de kit (16/09/2026).
 *
 * `categories.calculo_edad_id` ya existía desde la migración original,
 * `INT` firmado, sin FK — mismo caso que `formulario_id`/`sexo_id` antes de
 * sus respectivas migraciones de FK. `calculo_edades.id` es
 * `tinyIncrements()` (TINYINT UNSIGNED) — mismatch de tipo/signedness que
 * MySQL rechaza al crear la FK (errno 150), se corrige con SQL crudo antes
 * de agregarla, mismo criterio que
 * 2026_08_27_150000_add_formulario_id_foreign_key_to_categories_table.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE categories MODIFY calculo_edad_id TINYINT UNSIGNED NULL');

        Schema::table('categories', function (Blueprint $table) {
            $table->foreign('calculo_edad_id')->references('id')->on('calculo_edades')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropForeign(['calculo_edad_id']);
        });

        DB::statement('ALTER TABLE categories MODIFY calculo_edad_id INT NULL');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Categorías por form_type (27/08/2026) — ver
 * brain/api_rest_event/PLAN-CATEGORIAS-POR-FORM-TYPE-27082026.md.
 *
 * `categories.formulario_id` ya existía desde la migración original
 * (2026_07_01_150319_create_categories_table), nullable, pero la FK había
 * quedado comentada y ninguna capa de la app la usaba (siempre NULL).
 * Acá se agrega la FK real. NULL sigue significando "categoría compartida
 * por todos los form_types del evento" (comportamiento actual, decisión
 * explícita del usuario para no romper los eventos existentes que ya
 * combinan varios form_types con categorías sin esta separación).
 *
 * `formulario_id` se creó como INT firmado; `form_types.id` es
 * BIGINT UNSIGNED (`$table->id()`) — mismatch de tipo/signedness que
 * MySQL rechaza al crear la FK (errno 150). Se corrige el tipo con SQL
 * crudo antes de agregar la FK, mismo criterio que
 * 2026_07_23_020000_change_categoria_to_string_on_participantes_table
 * (no hay doctrine/dbal instalado para usar Schema::table()->change()).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE categories MODIFY formulario_id BIGINT UNSIGNED NULL');

        Schema::table('categories', function (Blueprint $table) {
            $table->foreign('formulario_id')->references('id')->on('form_types')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropForeign(['formulario_id']);
        });

        DB::statement('ALTER TABLE categories MODIFY formulario_id INT NULL');
    }
};

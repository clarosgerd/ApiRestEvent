<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `tipos_evento.id` y `subtipos_evento.id` nacieron `tinyIncrements` (máx
 * 255) — un catálogo tan chico en producción (7 tipos, 18 subtipos hoy) que
 * en su momento pareció suficiente. El problema es el AUTO_INCREMENT de
 * InnoDB: NO es transaccional, así que sobrevive los rollbacks de
 * `RefreshDatabase` — cualquier corrida completa de la suite de tests
 * (`php artisan test` sin filtro) crea un `TipoEvento`/`SubtipoEvento` por
 * cada Feature test (36 archivos lo hacen en su `setUp()`) y el contador
 * real de la tabla sube en cada intento, incluso los que se revierten.
 * Alrededor del test #256 de cualquier corrida completa, el INSERT
 * revienta con "Numeric value out of range" — no es un bug de los tests,
 * es que la columna se quedó chica para ese patrón de uso (03/09/2026,
 * hallado corriendo la suite completa durante el fix de TallaPoleraData).
 *
 * Ensanchado a `int unsigned` (máx ~4.29 mil millones) — mismo tipo que ya
 * usa `categories.id`, otro catálogo de tamaño similar; no hace falta
 * `bigint` para esto. Se ensanchan también las columnas FK que apuntan acá
 * (`subtipos_evento.tipo_evento_id`, `eventos.tipo_evento_id`,
 * `eventos.subtipo_evento_id`) — MySQL/InnoDB exige que una FK y la columna
 * que referencia tengan el mismo tipo, así que hay que tocar las cuatro
 * juntas. Se usa SQL crudo (no Blueprint::change()) porque estamos
 * modificando primary keys con AUTO_INCREMENT + sus foreign keys a la vez,
 * más previsible en MySQL que dejarlo en manos de doctrine/dbal.
 *
 * Puramente de tipo — ningún dato cambia, ningún id existente se
 * renumera. Los nombres de los constraints son los que Laravel generó por
 * convención al crearlos (`add_foreign_keys_to_eventos_table.php` /
 * `create_subtipos_evento_table.php`), confirmados contra
 * information_schema antes de escribir esta migración.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eventos', function ($table) {
            $table->dropForeign('eventos_tipo_evento_id_foreign');
            $table->dropForeign('eventos_subtipo_evento_id_foreign');
        });
        Schema::table('subtipos_evento', function ($table) {
            $table->dropForeign('subtipos_evento_tipo_evento_id_foreign');
        });

        DB::statement('ALTER TABLE tipos_evento MODIFY id INT UNSIGNED NOT NULL AUTO_INCREMENT');
        DB::statement('ALTER TABLE subtipos_evento MODIFY id INT UNSIGNED NOT NULL AUTO_INCREMENT');
        DB::statement('ALTER TABLE subtipos_evento MODIFY tipo_evento_id INT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE eventos MODIFY tipo_evento_id INT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE eventos MODIFY subtipo_evento_id INT UNSIGNED NOT NULL');

        Schema::table('subtipos_evento', function ($table) {
            $table->foreign('tipo_evento_id', 'subtipos_evento_tipo_evento_id_foreign')
                ->references('id')->on('tipos_evento');
        });
        Schema::table('eventos', function ($table) {
            $table->foreign('tipo_evento_id', 'eventos_tipo_evento_id_foreign')
                ->references('id')->on('tipos_evento');
            $table->foreign('subtipo_evento_id', 'eventos_subtipo_evento_id_foreign')
                ->references('id')->on('subtipos_evento');
        });
    }

    public function down(): void
    {
        // Vuelve a tinyint — solo tiene sentido si ningún id superó 255
        // desde que se corrió el up(). No se valida acá a propósito (una
        // migración down no debería mutar datos para "hacer caber" el
        // rollback) — si hace falta revertir con datos que ya no caben,
        // es una decisión manual, no automática.
        Schema::table('eventos', function ($table) {
            $table->dropForeign('eventos_tipo_evento_id_foreign');
            $table->dropForeign('eventos_subtipo_evento_id_foreign');
        });
        Schema::table('subtipos_evento', function ($table) {
            $table->dropForeign('subtipos_evento_tipo_evento_id_foreign');
        });

        DB::statement('ALTER TABLE subtipos_evento MODIFY tipo_evento_id TINYINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE eventos MODIFY tipo_evento_id TINYINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE eventos MODIFY subtipo_evento_id TINYINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE tipos_evento MODIFY id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT');
        DB::statement('ALTER TABLE subtipos_evento MODIFY id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT');

        Schema::table('subtipos_evento', function ($table) {
            $table->foreign('tipo_evento_id', 'subtipos_evento_tipo_evento_id_foreign')
                ->references('id')->on('tipos_evento');
        });
        Schema::table('eventos', function ($table) {
            $table->foreign('tipo_evento_id', 'eventos_tipo_evento_id_foreign')
                ->references('id')->on('tipos_evento');
            $table->foreign('subtipo_evento_id', 'eventos_subtipo_evento_id_foreign')
                ->references('id')->on('subtipos_evento');
        });
    }
};

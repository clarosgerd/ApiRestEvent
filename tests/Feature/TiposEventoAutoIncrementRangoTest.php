<?php

namespace Tests\Feature;

use App\Models\SubtipoEvento;
use App\Models\TipoEvento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `tipos_evento.id`/`subtipos_evento.id` nacieron `tinyIncrements` (máx
 * 255) — ver migración
 * `2026_09_03_154600_widen_tipos_evento_and_subtipos_evento_id_columns`.
 * El AUTO_INCREMENT de InnoDB no es transaccional (sobrevive los rollbacks
 * de `RefreshDatabase`), así que cualquier corrida completa de la suite
 * (36 archivos crean un TipoEvento/SubtipoEvento en su `setUp()`) subía el
 * contador real de la tabla en cada intento hasta desbordar la columna
 * alrededor del test #256, con `SQLSTATE[22003]: Numeric value out of
 * range`. No se puede reproducir corriendo 256 tests reales acá (sería un
 * test carísimo y frágil) — en cambio, se inserta directo un id > 255 y se
 * confirma que el insert funciona, que es exactamente lo que fallaba antes
 * del fix.
 *
 * Ojo: NO usar `DB::statement('ALTER TABLE ... AUTO_INCREMENT = ...')` acá
 * — un ALTER TABLE es DDL, y en MySQL el DDL hace commit implícito, lo que
 * corta la transacción que `RefreshDatabase` usa para revertir cada test
 * (se probó y dejó una fila real filtrada en `event_testing`, fuera de
 * cualquier rollback — limpiada a mano). Un INSERT con id explícito, en
 * cambio, es DML normal y sí queda protegido por la transacción de siempre.
 */
class TiposEventoAutoIncrementRangoTest extends TestCase
{
    use RefreshDatabase;

    public function test_admite_ids_mayores_a_255(): void
    {
        $tipo = TipoEvento::factory()->create(['id' => 300]);

        $this->assertSame(300, $tipo->id);
        $this->assertDatabaseHas('tipos_evento', ['id' => 300]);
    }

    public function test_subtipos_evento_admite_ids_mayores_a_255(): void
    {
        $tipoEvento = TipoEvento::factory()->create();

        $subtipo = SubtipoEvento::factory()->create(['id' => 300, 'tipo_evento_id' => $tipoEvento->id]);

        $this->assertSame(300, $subtipo->id);
        $this->assertDatabaseHas('subtipos_evento', ['id' => 300]);
    }
}

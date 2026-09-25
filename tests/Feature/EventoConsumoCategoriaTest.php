<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Participante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\ArmaExpositores;
use Tests\TestCase;

/**
 * GET /event/consumo — el nombre de la categoría de cada participante sale de
 * las categorías del evento ya cargadas, sin una consulta por participante.
 */
class EventoConsumoCategoriaTest extends TestCase
{
    use RefreshDatabase, ArmaExpositores;

    private function participanteConNumero(int $eventoId, string $categoria, int $n): void
    {
        $evento = \App\Models\Evento::findOrFail($eventoId);
        $p = $this->crearAsistente($evento, ['categoria' => $categoria]);
        Participante::whereKey($p->id)->update(['numero_corredor' => (string) $n, 'chip' => 'CH' . $n]);
    }

    public function test_devuelve_el_nombre_de_la_categoria_y_no_hace_una_consulta_por_participante(): void
    {
        $evento = $this->crearEvento(['estado_evento_id' => 'closed']);
        $cat = Category::factory()->create(['event_id' => $evento->id, 'name' => 'Elite Varones']);

        foreach (range(1, 3) as $n) {
            $this->participanteConNumero($evento->id, (string) $cat->id, $n);
        }
        $this->participanteConNumero($evento->id, 'categoria-suelta', 4);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->getJson('/api/v1/event/consumo?evento_id=' . $evento->id);
        $consultasCon4 = count(DB::getQueryLog());

        $response->assertOk();
        $participantes = collect($response->json('eventos.0.participantes'));
        $this->assertCount(4, $participantes);
        $this->assertSame(['Elite Varones'], $participantes->where('categoria', (string) $cat->id)->pluck('name')->unique()->values()->all());
        $this->assertSame('categoria-suelta', $participantes->firstWhere('categoria', 'categoria-suelta')['name'], 'Sin categoría conocida se conserva el valor original.');

        // Con más participantes no crecen las consultas.
        foreach (range(5, 12) as $n) {
            $this->participanteConNumero($evento->id, (string) $cat->id, $n);
        }
        DB::flushQueryLog();
        $this->getJson('/api/v1/event/consumo?evento_id=' . $evento->id)->assertOk();
        $this->assertSame($consultasCon4, count(DB::getQueryLog()));
    }
}

<?php

namespace Tests\Feature;

use App\Models\AgendaItem;
use App\Models\Ciudad;
use App\Models\Evento;
use App\Models\Organizador;
use App\Models\Pais;
use App\Models\SesionCongreso;
use App\Models\SubtipoEvento;
use App\Models\TipoEvento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Calendario del evento (05/10/2026) — GET /event/{event}/calendario. Une agenda y
 * sesiones de congreso ordenadas por fecha y hora, solo del evento pedido.
 */
class CalendarioEventoTest extends TestCase
{
    use RefreshDatabase;

    private Evento $evento;

    protected function setUp(): void
    {
        parent::setUp();

        $pais = Pais::factory()->create();
        $ciudad = Ciudad::factory()->create(['pais_id' => $pais->id]);
        $organizador = Organizador::factory()->create();
        $tipoEvento = TipoEvento::factory()->create();
        $subtipoEvento = SubtipoEvento::factory()->create(['tipo_evento_id' => $tipoEvento->id]);

        $this->evento = Evento::factory()->create([
            'organizador_id' => $organizador->id, 'tipo_evento_id' => $tipoEvento->id,
            'subtipo_evento_id' => $subtipoEvento->id, 'pais_id' => $pais->id, 'ciudad_id' => $ciudad->id,
            'fecha_inicio' => '2026-11-10', 'fecha_fin' => '2026-11-12',
        ]);
    }

    private function agenda(Evento $evento, string $fecha, string $inicio, string $titulo): void
    {
        AgendaItem::create([
            'event_id' => $evento->id, 'fecha' => $fecha, 'hora_inicio' => $inicio, 'hora_fin' => '10:00:00',
            'titulo' => $titulo, 'sala' => 'Sala A', 'ponente' => 'Dr. X', 'orden' => 1,
        ]);
    }

    public function test_devuelve_agenda_y_sesiones_ordenadas_por_fecha_y_hora(): void
    {
        $this->agenda($this->evento, '2026-11-11', '09:00:00', 'Agenda tarde');
        $this->agenda($this->evento, '2026-11-10', '08:00:00', 'Agenda primera');
        SesionCongreso::factory()->create([
            'evento_id' => $this->evento->id, 'fecha' => '2026-11-10', 'hora_inicio' => '07:30:00',
            'hora_fin' => '08:00:00', 'titulo' => 'Sesión temprana', 'sala' => 'Sala B',
        ]);

        $response = $this->getJson("/api/v1/event/{$this->evento->id}/calendario")->assertOk();

        $response->assertJsonPath('calendario.fecha_inicio', '2026-11-10');
        $response->assertJsonPath('calendario.fecha_fin', '2026-11-12');
        $bloques = $response->json('calendario.bloques');
        $this->assertCount(3, $bloques);
        $this->assertSame(['Sesión temprana', 'Agenda primera', 'Agenda tarde'], array_column($bloques, 'titulo'));
        $this->assertSame(['sesion', 'agenda', 'agenda'], array_column($bloques, 'tipo'));
        $this->assertSame('2026-11-10', $bloques[0]['fecha']);
    }

    public function test_no_incluye_bloques_de_otro_evento(): void
    {
        $otro = Evento::factory()->create([
            'organizador_id' => $this->evento->organizador_id, 'tipo_evento_id' => $this->evento->tipo_evento_id,
            'subtipo_evento_id' => $this->evento->subtipo_evento_id, 'pais_id' => $this->evento->pais_id,
            'ciudad_id' => $this->evento->ciudad_id,
        ]);
        $this->agenda($otro, '2026-11-10', '08:00:00', 'De otro evento');

        $bloques = $this->getJson("/api/v1/event/{$this->evento->id}/calendario")
            ->assertOk()
            ->json('calendario.bloques');

        $this->assertSame([], $bloques);
    }

    public function test_evento_sin_agenda_devuelve_lista_vacia_sin_error(): void
    {
        $this->getJson("/api/v1/event/{$this->evento->id}/calendario")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('calendario.bloques', []);
    }
}

<?php

namespace Tests\Feature;

use App\Actions\RealizarSorteoAction;
use App\Models\LeadCapturado;
use App\Models\Sorteo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ArmaExpositores;
use Tests\TestCase;

/**
 * SmartStand fase 4 — sorteo digital de una empresa entre sus propios contactos.
 */
class SorteoExpositorTest extends TestCase
{
    use RefreshDatabase, ArmaExpositores;

    private function lead($cuenta, $asistente, ?int $calificacion = null): LeadCapturado
    {
        return LeadCapturado::create([
            'empresa_expositora_id' => $cuenta->id,
            'participante_id'       => $asistente->id,
            'calificacion'          => $calificacion,
            'capturado_at'          => now(),
        ]);
    }

    public function test_sortea_entre_los_contactos_de_la_empresa_y_guarda_el_registro(): void
    {
        $evento = $this->crearEvento();
        $cuenta = $this->crearCuenta($evento);
        $a = $this->crearAsistente($evento, ['nombre' => 'Ana']);
        $b = $this->crearAsistente($evento, ['nombre' => 'Beto']);
        $this->lead($cuenta, $a);
        $this->lead($cuenta, $b);
        $this->comoExpositor($cuenta);

        $r = $this->postJson('/api/v1/expositor/sorteos', ['premio' => 'Tablet']);

        $r->assertCreated()->assertJsonPath('sorteo.premio', 'Tablet')->assertJsonPath('sorteo.candidatosCount', 2);
        $sorteo = Sorteo::firstOrFail();
        $this->assertContains($sorteo->participante_ganador_id, [$a->id, $b->id]);
        $this->assertSame($cuenta->id, $sorteo->empresa_expositora_id);
        $this->assertSame(Sorteo::TIPO_EXPOSITOR, $sorteo->tipo);
        $this->assertSame(64, strlen($sorteo->candidatos_hash));
        $this->assertNotEmpty($sorteo->ganador['nombre']);
        $this->assertNotNull($sorteo->sorteado_at);
    }

    public function test_no_incluye_contactos_de_otra_empresa(): void
    {
        $evento = $this->crearEvento();
        $cuenta = $this->crearCuenta($evento);
        $otra = $this->crearCuenta($evento);
        $mio = $this->crearAsistente($evento);
        $ajeno = $this->crearAsistente($evento);
        $this->lead($cuenta, $mio);
        $this->lead($otra, $ajeno);
        $this->comoExpositor($cuenta);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/expositor/sorteos', ['premio' => 'P', 'excluir_ganadores' => false])
                ->assertCreated();
        }

        $this->assertSame([$mio->id], Sorteo::pluck('participante_ganador_id')->unique()->values()->all());
    }

    public function test_excluye_ganadores_previos_y_da_422_cuando_no_quedan(): void
    {
        $evento = $this->crearEvento();
        $cuenta = $this->crearCuenta($evento);
        $this->lead($cuenta, $this->crearAsistente($evento));
        $this->lead($cuenta, $this->crearAsistente($evento));
        $this->comoExpositor($cuenta);

        $this->postJson('/api/v1/expositor/sorteos', ['premio' => 'P1'])->assertCreated();
        $this->postJson('/api/v1/expositor/sorteos', ['premio' => 'P2'])->assertCreated();
        $this->postJson('/api/v1/expositor/sorteos', ['premio' => 'P3'])->assertStatus(422)->assertJsonPath('success', false);

        $this->assertSame(2, Sorteo::pluck('participante_ganador_id')->unique()->count(), 'Nadie ganó dos veces.');
    }

    public function test_respeta_la_calificacion_minima(): void
    {
        $evento = $this->crearEvento();
        $cuenta = $this->crearCuenta($evento);
        $bajo = $this->crearAsistente($evento);
        $alto = $this->crearAsistente($evento);
        $sin = $this->crearAsistente($evento);
        $this->lead($cuenta, $bajo, 2);
        $this->lead($cuenta, $alto, 5);
        $this->lead($cuenta, $sin, null);
        $this->comoExpositor($cuenta);

        $r = $this->postJson('/api/v1/expositor/sorteos', ['premio' => 'P', 'min_calificacion' => 4]);

        $r->assertCreated()->assertJsonPath('sorteo.candidatosCount', 1);
        $this->assertSame($alto->id, Sorteo::firstOrFail()->participante_ganador_id);
    }

    public function test_sin_contactos_da_422_y_premio_es_obligatorio(): void
    {
        $cuenta = $this->crearCuenta($this->crearEvento());
        $this->comoExpositor($cuenta);

        $this->postJson('/api/v1/expositor/sorteos', ['premio' => 'P'])->assertStatus(422);
        $this->postJson('/api/v1/expositor/sorteos', [])->assertStatus(422)->assertJsonValidationErrors('premio');
    }

    public function test_el_historial_solo_muestra_los_sorteos_de_la_propia_empresa(): void
    {
        $evento = $this->crearEvento();
        $cuenta = $this->crearCuenta($evento);
        $otra = $this->crearCuenta($evento);
        $this->lead($cuenta, $this->crearAsistente($evento));
        $this->lead($otra, $this->crearAsistente($evento));
        $this->comoExpositor($otra);
        $this->postJson('/api/v1/expositor/sorteos', ['premio' => 'Ajeno'])->assertCreated();
        $this->comoExpositor($cuenta);
        $this->postJson('/api/v1/expositor/sorteos', ['premio' => 'Mío'])->assertCreated();

        $this->getJson('/api/v1/expositor/sorteos')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.premio', 'Mío');
    }

    public function test_exige_sesion_de_expositor(): void
    {
        $this->postJson('/api/v1/expositor/sorteos', ['premio' => 'P'])->assertUnauthorized();

        // Un token de admin no vale: el guard es `expositores`.
        $this->actingAsAdmin();
        $this->postJson('/api/v1/expositor/sorteos', ['premio' => 'P'])->assertUnauthorized();
    }

    public function test_la_eleccion_es_uniforme_y_siempre_de_la_lista(): void
    {
        $accion = new RealizarSorteoAction();
        $ids = collect([10, 20, 30, 40]);
        $conteo = array_fill_keys($ids->all(), 0);

        for ($i = 0; $i < 4000; $i++) {
            $conteo[$accion->elegir($ids)['ganador_id']]++;
        }

        foreach ($conteo as $id => $veces) {
            $this->assertGreaterThan(800, $veces, "El id {$id} salió muy poco: {$veces}/4000");
            $this->assertLessThan(1200, $veces, "El id {$id} salió demasiado: {$veces}/4000");
        }
    }

    public function test_el_hash_no_depende_del_orden_ni_de_duplicados_y_lista_vacia_es_null(): void
    {
        $accion = new RealizarSorteoAction();

        $a = $accion->elegir(collect([3, 1, 2, 2]));
        $b = $accion->elegir(collect([1, 2, 3]));

        $this->assertSame($a['candidatos_hash'], $b['candidatos_hash']);
        $this->assertSame(3, $a['candidatos_count']);
        $this->assertNull($accion->elegir(collect()));
    }
}

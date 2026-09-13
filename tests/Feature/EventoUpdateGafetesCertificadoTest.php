<?php

namespace Tests\Feature;

use App\Models\Ciudad;
use App\Models\Evento;
use App\Models\Organizador;
use App\Models\Pais;
use App\Models\SubtipoEvento;
use App\Models\TipoEvento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Wiring de EventoService::update() -> gafete_config/certificado_solo_nombre
 * (13/09/2026) — mismo patrón que usdPrecioFijo/seccionesOrden: sin la
 * entrada en el $map, el campo se valida pero nunca se persiste.
 */
class EventoUpdateGafetesCertificadoTest extends TestCase
{
    use RefreshDatabase;

    private function crearEvento(): Evento
    {
        $pais = Pais::factory()->create();
        $ciudad = Ciudad::factory()->create(['pais_id' => $pais->id]);
        $organizador = Organizador::factory()->create();
        $tipoEvento = TipoEvento::factory()->create();
        $subtipoEvento = SubtipoEvento::factory()->create(['tipo_evento_id' => $tipoEvento->id]);

        return Evento::factory()->create([
            'organizador_id' => $organizador->id,
            'tipo_evento_id' => $tipoEvento->id,
            'subtipo_evento_id' => $subtipoEvento->id,
            'pais_id' => $pais->id,
            'ciudad_id' => $ciudad->id,
            'certificado_solo_nombre' => false,
            'gafete_config' => null,
        ]);
    }

    public function test_update_persiste_certificado_solo_nombre_y_gafete_config(): void
    {
        $this->actingAsAdmin();
        $evento = $this->crearEvento();

        $response = $this->putJson("/api/v1/event/{$evento->id}", [
            'certificadoSoloNombre' => true,
            'gafeteConfig' => ['width_cm' => 8, 'height_cm' => 6, 'per_row' => 2, 'paper' => 'letter', 'orientation' => 'portrait'],
        ]);

        $response->assertStatus(200);

        $evento->refresh();
        $this->assertTrue((bool) $evento->certificado_solo_nombre);
        $this->assertSame(8.0, (float) $evento->gafete_config['width_cm']);
        $this->assertSame(2, (int) $evento->gafete_config['per_row']);
        $this->assertSame('letter', $evento->gafete_config['paper']);
        $this->assertSame('portrait', $evento->gafete_config['orientation']);
    }

    /** Omitir estos campos en el update no los pisa (sometimes en el Request). */
    public function test_update_sin_estos_campos_no_los_modifica(): void
    {
        $this->actingAsAdmin();
        $evento = $this->crearEvento();
        $evento->update(['certificado_solo_nombre' => true, 'gafete_config' => ['width_cm' => 9]]);

        $response = $this->putJson("/api/v1/event/{$evento->id}", [
            'nombre' => $evento->nombre,
        ]);

        $response->assertStatus(200);

        $evento->refresh();
        $this->assertTrue((bool) $evento->certificado_solo_nombre);
        $this->assertSame(9.0, (float) $evento->gafete_config['width_cm']);
    }

    /** gafeteConfig.per_row fuera de rango es rechazado por la validación. */
    public function test_update_rechaza_per_row_fuera_de_rango(): void
    {
        $this->actingAsAdmin();
        $evento = $this->crearEvento();

        $response = $this->putJson("/api/v1/event/{$evento->id}", [
            'gafeteConfig' => ['per_row' => 99],
        ]);

        $response->assertStatus(422);
    }
}

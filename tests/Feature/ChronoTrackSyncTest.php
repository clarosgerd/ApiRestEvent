<?php

namespace Tests\Feature;

use App\Models\Evento;
use App\Models\FormType;
use App\Models\Registration;
use App\Models\Resultado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * POST /event/{event}/chronotrack/sincronizar — App\Actions\SincronizarChronoTrackAction.
 * No existía ningún test automatizado antes de esta suite; la sync solo se
 * había probado a mano contra la API real de ChronoTrack.
 *
 * Se usa Http::fake() (decisión confirmada con el usuario) en vez de la API
 * real — rápido, determinístico, no gasta cuota ni depende de que
 * ChronoTrack esté arriba. No prueba la integración real de punta a punta,
 * solo la lógica de DNS/DNF/mapeo de campos/matching de participantes.
 */
class ChronoTrackSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Sin esto, ChronoTrackClient::get() lanza RuntimeException antes
        // de llegar a Http::get() — el chequeo de credenciales pasa
        // primero, independientemente de que la HTTP esté fakeada.
        config([
            'services.chronotrack.base_url'  => 'https://fake-chronotrack.test/api',
            'services.chronotrack.client_id' => 'fake-client',
            'services.chronotrack.user_id'   => 'fake-user',
            'services.chronotrack.user_pass' => 'fake-pass',
        ]);
    }

    private function fakeChronoTrackApi(): void
    {
        Http::fake([
            'fake-chronotrack.test/api/event/999/interval*' => Http::response([
                'event_interval' => [
                    ['interval_id' => '501', 'interval_is_full' => '1', 'race_id' => '601', 'race_name' => '10K'],
                    ['interval_id' => '502', 'interval_is_full' => '0', 'race_id' => '601', 'race_name' => '10K'],
                ],
            ]),
            'fake-chronotrack.test/api/interval/501/results*' => Http::response([
                // Finisher real — matchea al Participante con numero_corredor=101.
                'interval_results' => [[
                    'results_bib' => '101',
                    'results_gun_time_with_penalty' => '00:45:00.000',
                    'results_rank' => '1',
                ]],
            ]),
            'fake-chronotrack.test/api/interval/502/results*' => Http::response([
                // Bib 102 llegó al checkpoint parcial pero no al completo -> dnf.
                'interval_results' => [[
                    'results_bib' => '102',
                ]],
            ]),
            'fake-chronotrack.test/api/race/601/entry*' => Http::response([
                // 101 finisher, 102 dnf, 103 nunca apareció en ningún timing point -> dns.
                'race_entry' => [
                    ['entry_bib' => '101'],
                    ['entry_bib' => '102'],
                    ['entry_bib' => '103'],
                ],
            ]),
        ]);
    }

    private function crearEventoConParticipanteBib101(): Evento
    {
        $evento = Evento::factory()->create(['chronotrack_event_id' => '999']);
        $formType = FormType::factory()->create(['event_id' => $evento->id]);
        $registration = Registration::create([
            'referencia'    => 'REF-CT-SYNC-TEST',
            'fecha'         => now(),
            'evento_id'     => $evento->id,
            'form_types_id' => $formType->id,
            'evento_nombre' => $evento->nombre,
            'tipo_pago'     => 'qr',
            'pago_status'   => 'paid',
        ]);
        \App\Models\Participante::create([
            'registration_id'  => $registration->id,
            'nombre'            => 'Juan',
            'apellido'          => 'Perez',
            'alias'             => '',
            'genero'            => 'Masculino',
            'tipo_documento'    => 'CI',
            'numero_documento'  => '9999901',
            'numero_corredor'   => '101',
            'fecha_nacimiento'  => '1990-01-01',
            'edad'              => 36,
            'correo'            => 'juan.perez.ctsynctest@example.net',
            'direccion'         => 'x',
            'ciudad'            => 'x',
            'telefono'          => 'x',
            'categoria'         => '1',
            'precio_categoria'  => 0,
            'donacion'          => 0,
            'promo_descuento'   => 0,
            'promo_codigo'      => '',
            'subtotal'          => 0,
        ]);

        return $evento;
    }

    public function test_sincronizar_detects_finisher_dnf_and_dns(): void
    {
        $this->fakeChronoTrackApi();
        $this->actingAsAdmin();
        $evento = $this->crearEventoConParticipanteBib101();

        $response = $this->postJson("/api/v1/event/{$evento->id}/chronotrack/sincronizar");

        $response->assertStatus(200)->assertJson([
            'success'   => true,
            'procesados' => 3,
            'intervals' => 1,
            'dns'       => 1,
            'dnf'       => 1,
        ]);

        $finisher = Resultado::where('event_id', $evento->id)->where('numero_corredor', '101')->first();
        $this->assertSame('finisher', $finisher->estado);
        $this->assertNotNull($finisher->participante_id);
        $this->assertSame('00:45:00.000', $finisher->tiempo_oficial);

        $dnf = Resultado::where('event_id', $evento->id)->where('numero_corredor', '102')->first();
        $this->assertSame('dnf', $dnf->estado);
        $this->assertNull($dnf->participante_id);

        $dns = Resultado::where('event_id', $evento->id)->where('numero_corredor', '103')->first();
        $this->assertSame('dns', $dns->estado);
        $this->assertNull($dns->participante_id);
    }

    public function test_sincronizar_requires_chronotrack_event_id_configured(): void
    {
        $this->actingAsAdmin();
        $evento = Evento::factory()->create(['chronotrack_event_id' => null]);

        $this->postJson("/api/v1/event/{$evento->id}/chronotrack/sincronizar")
            ->assertStatus(422)
            ->assertJson(['success' => false]);
    }
}

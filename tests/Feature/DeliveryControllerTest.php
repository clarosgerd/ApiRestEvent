<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ciudad;
use App\Models\Evento;
use App\Models\FormType;
use App\Models\Organizador;
use App\Models\Pais;
use App\Models\Participante;
use App\Models\Registration;
use App\Models\Souvenir;
use App\Models\SouvenirParticipante;
use App\Models\SubtipoEvento;
use App\Models\TipoEvento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Dashboard/CSV/JSON de delivery de kits (sin login, link firmado) — ver
 * DeliveryController. Cubre puntualmente el fix de talla real de la polera
 * (03/09/2026, ver App\Support\TallaPoleraData): antes de este fix, las 3
 * salidas (CSV, JSON, y la vista HTML) leían directo
 * `participantes.polera`, un campo legacy que queda siempre en el sentinel
 * 'No shirt' para eventos con la polera modelada como souvenir normal — el
 * mismo bug que el usuario encontró primero en el Reporte de poleras del
 * dashboard del organizador.
 */
class DeliveryControllerTest extends TestCase
{
    use RefreshDatabase;

    private Evento $evento;

    private FormType $formType;

    protected function setUp(): void
    {
        parent::setUp();

        $pais = Pais::factory()->create();
        $ciudad = Ciudad::factory()->create(['pais_id' => $pais->id]);
        $organizador = Organizador::factory()->create();
        $tipoEvento = TipoEvento::factory()->create();
        $subtipoEvento = SubtipoEvento::factory()->create(['tipo_evento_id' => $tipoEvento->id]);

        $this->evento = Evento::factory()->create([
            'organizador_id' => $organizador->id,
            'tipo_evento_id' => $tipoEvento->id,
            'subtipo_evento_id' => $subtipoEvento->id,
            'pais_id' => $pais->id,
            'ciudad_id' => $ciudad->id,
        ]);

        $this->formType = FormType::factory()->create(['event_id' => $this->evento->id]);
    }

    private function crearParticipanteConDelivery(array $overrides = []): Participante
    {
        $registration = Registration::create([
            'referencia' => 'REF' . rand(100000, 999999),
            'fecha' => now(),
            'evento_id' => $this->evento->id,
            'form_types_id' => $this->formType->id,
            'evento_nombre' => $this->evento->nombre,
            'tipo_pago' => 'EFECTIVO',
            'pago_status' => 'paid',
        ]);

        return Participante::create(array_merge([
            'registration_id' => $registration->id,
            'nombre' => 'Ana', 'apellido' => 'Prueba', 'genero' => 'Femenino',
            'tipo_documento' => 'DNI', 'numero_documento' => (string) rand(1000000, 9999999),
            'fecha_nacimiento' => '1995-01-01', 'edad' => 30,
            'correo' => 'ana' . rand(1, 999999) . '@test.net', 'direccion' => 'x', 'ciudad' => 'x', 'telefono' => '123',
            'categoria' => (string) Category::factory()->create(['event_id' => $this->evento->id])->id,
            'precio_categoria' => 50, 'subtotal' => 50,
            'quiere_delivery' => true, 'estado_delivery' => 'pendiente',
            'polera' => 'No shirt',
        ], $overrides));
    }

    public function test_csv_muestra_la_talla_real_del_souvenir_marcado_es_polera(): void
    {
        $polera = Souvenir::factory()->create([
            'form_types_id' => $this->formType->id, 'requiere_talla' => true, 'es_polera' => true,
        ]);
        $p = $this->crearParticipanteConDelivery();
        SouvenirParticipante::create([
            'participante_id' => $p->id, 'souvenir_id' => $polera->id,
            'nombre' => $polera->name, 'precio' => $polera->price, 'talla' => 'XL',
        ]);

        $url = URL::signedRoute('delivery.dashboard.export', ['evento' => $this->evento->id]);
        $csv = $this->get($url)->assertOk()->streamedContent();

        $lines = array_map('str_getcsv', explode("\n", trim($csv)));
        $header = $lines[0];
        $tallaIdx = array_search('Talla/Polera', $header);
        $docIdx = array_search('Documento', $header);

        $this->assertNotFalse($tallaIdx);
        $fila = collect($lines)->first(fn ($l) => str_contains($l[$docIdx] ?? '', $p->numero_documento));

        $this->assertSame('XL', $fila[$tallaIdx]);
    }

    public function test_csv_sin_souvenir_es_polera_cae_al_campo_legacy(): void
    {
        $p = $this->crearParticipanteConDelivery(['polera' => 'L']);

        $url = URL::signedRoute('delivery.dashboard.export', ['evento' => $this->evento->id]);
        $csv = $this->get($url)->assertOk()->streamedContent();

        $lines = array_map('str_getcsv', explode("\n", trim($csv)));
        $header = $lines[0];
        $tallaIdx = array_search('Talla/Polera', $header);
        $docIdx = array_search('Documento', $header);

        $fila = collect($lines)->first(fn ($l) => str_contains($l[$docIdx] ?? '', $p->numero_documento));

        $this->assertSame('L', $fila[$tallaIdx]);
    }

    public function test_json_muestra_la_talla_real_del_souvenir_marcado_es_polera(): void
    {
        $polera = Souvenir::factory()->create([
            'form_types_id' => $this->formType->id, 'requiere_talla' => true, 'es_polera' => true,
        ]);
        $p = $this->crearParticipanteConDelivery();
        SouvenirParticipante::create([
            'participante_id' => $p->id, 'souvenir_id' => $polera->id,
            'nombre' => $polera->name, 'precio' => $polera->price, 'talla' => 'S',
        ]);

        $url = URL::signedRoute('delivery.dashboard.json', ['evento' => $this->evento->id]);
        $response = $this->getJson($url)->assertOk();

        $fila = collect($response->json('participantes'))->firstWhere('id', $p->id);
        $this->assertSame('S', $fila['talla']);
    }
}

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
use App\Models\SubtipoEvento;
use App\Models\TipoEvento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Lookup de QR para app externa Android/iOS (12/09/2026) — ver
 * brain/api_rest_event/PLAN-QR-LOOKUP-APP-EXTERNA-12092026.md.
 * A propósito devuelve MUCHO menos que GET /registrations/{reference}
 * (público, sin secreto) — acá se verifica explícitamente que NO se
 * expone documento/correo/teléfono/nacimiento/totales.
 */
class QrLookupTest extends TestCase
{
    use RefreshDatabase;

    private Evento $evento;

    private FormType $formType;

    private Registration $registration;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.external_qr_lookup.secret' => 'test-qr-secret-123']);

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
            'nombre' => 'Evento de prueba QR',
        ]);

        $this->formType = FormType::factory()->create([
            'event_id' => $this->evento->id,
            'name' => 'Congresista',
        ]);

        $this->registration = Registration::factory()->create([
            'referencia' => 'LA-QRTEST01',
            'fecha' => now(),
            'evento_id' => $this->evento->id,
            'form_types_id' => $this->formType->id,
            'evento_nombre' => $this->evento->nombre,
            'tipo_pago' => 'QR',
            'pago_status' => 'paid',
        ]);

        // Participante::create() directo, no factory() — la factory de este
        // modelo quedó desactualizada contra el schema real (columnas
        // email/password/sexo/celular/token que no existen), mismo
        // criterio ya usado en SincronizarParticipanteExternoAction.
        Participante::create([
            'registration_id' => $this->registration->id,
            'nombre' => 'Ana', 'apellido' => 'Gutierrez', 'genero' => 'Femenino',
            'tipo_documento' => 'CI', 'numero_documento' => '99999999',
            'fecha_nacimiento' => '1995-01-01', 'edad' => 30,
            'correo' => 'ana@secreto.net', 'direccion' => 'Calle Secreta 123',
            'ciudad' => 'x', 'telefono' => '77712345',
            'categoria' => 'Estudiante', 'subtotal' => 0,
        ]);
    }

    private function getLookup(string $referencia, ?string $secret = 'test-qr-secret-123'): \Illuminate\Testing\TestResponse
    {
        $headers = $secret !== null ? ['X-External-Qr-Lookup-Secret' => $secret] : [];

        return $this->getJson("/api/v1/internal/qr-lookup/{$referencia}", $headers);
    }

    public function test_rechaza_sin_secreto(): void
    {
        $this->getLookup($this->registration->referencia, null)->assertStatus(403);
    }

    public function test_rechaza_con_secreto_incorrecto(): void
    {
        $this->getLookup($this->registration->referencia, 'secreto-equivocado')->assertStatus(403);
    }

    public function test_devuelve_datos_minimos_del_participante(): void
    {
        $response = $this->getLookup($this->registration->referencia)->assertOk();

        $response->assertJson([
            'success' => true,
            'data' => [
                'referencia' => 'LA-QRTEST01',
                'evento_nombre' => 'Evento de prueba QR',
                'pago_status' => 'paid',
                'participantes' => [
                    ['nombre' => 'Ana', 'apellido' => 'Gutierrez', 'categoria' => 'Estudiante', 'rol' => 'Congresista', 'checkedInAt' => null],
                ],
            ],
        ]);
    }

    /**
     * El corazón de este endpoint: NO debe filtrar nada de lo que sí
     * expone GET /registrations/{reference} (público, sin secreto) —
     * documento, correo, teléfono, dirección, nacimiento, montos.
     */
    public function test_no_expone_datos_sensibles_del_participante(): void
    {
        $json = $this->getLookup($this->registration->referencia)->assertOk()->json();
        $textoCompleto = json_encode($json);

        $this->assertStringNotContainsString('99999999', $textoCompleto);
        $this->assertStringNotContainsString('secreto.net', $textoCompleto);
        $this->assertStringNotContainsString('77712345', $textoCompleto);
        $this->assertStringNotContainsString('Calle Secreta', $textoCompleto);
        $this->assertArrayNotHasKey('totales', $json['data']);
        $this->assertArrayNotHasKey('totalPagado', $json['data']);
        $this->assertArrayNotHasKey('numeroDocumento', $json['data']['participantes'][0]);
        $this->assertArrayNotHasKey('correo', $json['data']['participantes'][0]);
    }

    public function test_resuelve_categoria_por_id_cuando_el_form_type_usa_categorias_reales(): void
    {
        $formTypeConCategoria = FormType::factory()->create(['event_id' => $this->evento->id, 'name' => 'Con categoría', 'requiere_categoria' => true]);
        $categoria = Category::factory()->create(['event_id' => $this->evento->id, 'name' => '15K', 'price' => 100]);
        $registration = Registration::factory()->create([
            'referencia' => 'LA-QRTEST02', 'fecha' => now(), 'evento_id' => $this->evento->id,
            'form_types_id' => $formTypeConCategoria->id, 'evento_nombre' => $this->evento->nombre,
            'tipo_pago' => 'QR', 'pago_status' => 'paid',
        ]);
        Participante::create([
            'registration_id' => $registration->id, 'nombre' => 'Carlos', 'apellido' => 'Perez', 'genero' => 'Masculino',
            'tipo_documento' => 'CI', 'numero_documento' => '88888888',
            'fecha_nacimiento' => '1990-01-01', 'edad' => 35,
            'correo' => 'carlos@test.net', 'direccion' => 'x', 'ciudad' => 'x', 'telefono' => 'x',
            'categoria' => (string) $categoria->id, 'subtotal' => 0,
        ]);

        $this->getLookup('LA-QRTEST02')->assertOk()->assertJsonFragment(['categoria' => '15K']);
    }

    public function test_referencia_inexistente_da_404(): void
    {
        $this->getLookup('LA-NOEXISTE')->assertStatus(404);
    }

    public function test_expone_checked_in_at_cuando_ya_se_acredito(): void
    {
        $participante = $this->registration->participants()->first();
        $participante->update(['checked_in_at' => now()]);

        $response = $this->getLookup($this->registration->referencia)->assertOk();

        $this->assertNotNull($response->json('data.participantes.0.checkedInAt'));
    }
}

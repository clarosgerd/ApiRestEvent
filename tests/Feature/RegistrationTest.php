<?php

namespace Tests\Feature;

use App\Models\Evento;
use App\Models\FormType;
use App\Models\Registration;
use App\Models\Participante;
use App\Models\RegistrationTotal;
use App\Models\ContactoEmergenciaParticipante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private Evento $event;
    private FormType $formType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsPersona();
        $this->event = Evento::factory()->create();
        $this->formType = FormType::factory()->create([
            'event_id' => $this->event->id,
        ]);
    }

    private function validPayload(array $overrides = []): array
    {
        $reference = 'REF-' . Str::random(8);

        $participant = array_merge([
            'nombre' => 'Ana',
            'apellido' => 'Garcia',
            'alias' => 'anita',
            'genero' => 'Femenino',
            'tipoDocumento' => 'CI',
            'numeroDocumento' => '87654321',
            'polera' => 'M',
            'precioPolera' => 0,
            'nacimiento' => [
                'dia' => 10,
                'mes' => 5,
                'anio' => 1995,
            ],
            'edad' => 31,
            'correo' => 'ana@example.com',
            'direccion' => 'Av. Siempre Viva 123',
            'ciudad' => 'Santa Cruz',
            'telefono' => '22001122',
            'categoria' => 1,
            'precioCategoria' => 100,
            'donacion' => 0,
            'promoDescuento' => 0,
            'promoCodigo' => '',
            'subtotal' => 100,
            'contacto_emergencia' => [
                'nombre' => 'Luis Garcia',
                'celular' => '099111111',
                'relacion' => 'Padre',
            ],
            'souvenirs' => [],
        ], $overrides);

        return [[
            'referencia' => $reference,
            'fecha' => now()->toDateTimeString(),
            'evento_id' => $this->event->id,
            'form_types_id' => $this->formType->id,
            'evento_nombre' => $this->event->nombre,
            'tipo_pago' => 'QR',
            'pago_status' => 'pending',
            'totales' => [
                'inscripcion' => 100,
                'donacion' => 0,
                'souvenirs' => 0,
                'fee' => 0,
                'descuento' => 0,
                'grand_total' => 100,
            ],
            'participantes' => [$participant],
        ]];
    }

    // ==========================================
    // 3.1 CREAR INSCRIPCION
    // ==========================================

    public function test_create_registration_returns_201(): void
    {
        $this->postJson('/api/v1/registrations', $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'message', 'data']);
    }

    public function test_create_registration_stores_in_database(): void
    {
        $payload = $this->validPayload();
        $reference = $payload[0]['referencia'];

        $this->postJson('/api/v1/registrations', $payload);

        $this->assertDatabaseHas('registrations', ['referencia' => $reference]);
        $this->assertDatabaseHas('registration_totals', [
            'inscripcion' => 100,
            'grand_total' => 100,
        ]);
    }

    public function test_create_registration_stores_participant(): void
    {
        $this->postJson('/api/v1/registrations', $this->validPayload());

        $this->assertDatabaseHas('participantes', [
            'nombre' => 'Ana',
            'apellido' => 'Garcia',
            'numero_documento' => '87654321',
        ]);
    }

    public function test_create_registration_stores_emergency_contact(): void
    {
        $this->postJson('/api/v1/registrations', $this->validPayload());

        $participant = Participante::where('numero_documento', '87654321')->first();
        $this->assertDatabaseHas('contacto_emergencia_participantes', [
            'participante_id' => $participant->id,
            'nombre' => 'Luis Garcia',
            'celular' => '099111111',
        ]);
    }

    public function test_create_registration_with_multiple_participants(): void
    {
        $payload = $this->validPayload();
        $payload[0]['participantes'][] = [
            'nombre' => 'Carlos',
            'apellido' => 'Lopez',
            'alias' => 'clopez',
            'genero' => 'Masculino',
            'tipoDocumento' => 'CI',
            'numeroDocumento' => '11223344',
            'polera' => 'L',
            'precioPolera' => 0,
            'nacimiento' => ['dia' => 20, 'mes' => 8, 'anio' => 1988],
            'edad' => 38,
            'correo' => 'carlos@example.com',
            'direccion' => 'Calle 456',
            'ciudad' => 'La Paz',
            'telefono' => '22113344',
            'categoria' => 1,
            'precioCategoria' => 100,
            'donacion' => 0,
            'promoDescuento' => 0,
            'promoCodigo' => '',
            'subtotal' => 100,
            'contacto_emergencia' => [
                'nombre' => 'Maria Lopez',
                'celular' => '099222222',
                'relacion' => 'Madre',
            ],
            'souvenirs' => [],
        ];

        $this->postJson('/api/v1/registrations', $payload)
            ->assertCreated();

        $this->assertDatabaseCount('participantes', 2);
    }

    public function test_create_registration_rejects_empty_participants(): void
    {
        $payload = $this->validPayload();
        $payload[0]['participantes'] = [];

        $this->postJson('/api/v1/registrations', $payload)
            ->assertUnprocessable();
    }

    public function test_create_registration_rejects_missing_reference(): void
    {
        $payload = $this->validPayload();
        unset($payload[0]['referencia']);

        $this->postJson('/api/v1/registrations', $payload)
            ->assertUnprocessable();
    }

    public function test_create_registration_rejects_missing_totals(): void
    {
        $payload = $this->validPayload();
        unset($payload[0]['totales']);

        $this->postJson('/api/v1/registrations', $payload)
            ->assertUnprocessable();
    }

    public function test_create_registration_rejects_missing_emergency_contact(): void
    {
        $payload = $this->validPayload();
        unset($payload[0]['participantes'][0]['contacto_emergencia']);

        $this->postJson('/api/v1/registrations', $payload)
            ->assertUnprocessable();
    }

    public function test_create_registration_rejects_missing_participant_email(): void
    {
        $payload = $this->validPayload();
        unset($payload[0]['participantes'][0]['correo']);

        $this->postJson('/api/v1/registrations', $payload)
            ->assertUnprocessable();
    }

    public function test_create_registration_stores_totals_correctly(): void
    {
        $this->postJson('/api/v1/registrations', $this->validPayload());

        $total = RegistrationTotal::first();
        $this->assertEquals('100.00', $total->inscripcion);
        $this->assertEquals('0.00', $total->donacion);
        $this->assertEquals('100.00', $total->grand_total);
    }

    public function test_create_registration_returns_reference_in_response(): void
    {
        $payload = $this->validPayload();
        $reference = $payload[0]['referencia'];

        $this->postJson('/api/v1/registrations', $payload)
            ->assertCreated()
            ->assertJsonPath('data.referencia', $reference);
    }

    public function test_duplicate_reference_throws_exception(): void
    {
        $payload = $this->validPayload();
        $this->postJson('/api/v1/registrations', $payload)->assertCreated();

        $this->postJson('/api/v1/registrations', $payload)
            ->assertStatus(500);
    }

    public function test_duplicate_participant_in_same_request_throws(): void
    {
        $payload = $this->validPayload();
        $participant = $payload[0]['participantes'][0];
        $payload[0]['participantes'][] = $participant;

        // validateDuplicateParticipants() lanza \DomainException, que
        // RegistrationController::store() ya captura y convierte en 422
        // (a diferencia de la referencia duplicada, que es \Exception plana
        // y sí llega como 500 sin capturar — ver test de arriba).
        $this->postJson('/api/v1/registrations', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    // ==========================================
    // 3.2 LISTAR INSCRIPCIONES
    // ==========================================

    public function test_list_registrations_returns_paginated(): void
    {
        $this->getJson('/api/v1/registrations')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'items',
                    'pagination' => ['current_page', 'last_page', 'per_page', 'total'],
                ],
            ]);
    }

    public function test_list_registrations_returns_created_items(): void
    {
        $this->postJson('/api/v1/registrations', $this->validPayload())->assertCreated();

        $secondPayload = $this->validPayload();
        $secondPayload[0]['participantes'][0]['numeroDocumento'] = '99887766';
        $secondPayload[0]['participantes'][0]['correo'] = 'carlos@example.com';
        $this->postJson('/api/v1/registrations', $secondPayload)->assertCreated();

        $response = $this->getJson('/api/v1/registrations')->assertOk();

        $this->assertCount(2, $response->json('data.items'));
    }

    public function test_list_registrations_filter_by_event_id(): void
    {
        $event2 = Evento::factory()->create();

        $this->postJson('/api/v1/registrations', $this->validPayload())->assertCreated();

        $payload2 = $this->validPayload();
        $payload2[0]['evento_id'] = $event2->id;
        $this->postJson('/api/v1/registrations', $payload2)->assertCreated();

        $response = $this->getJson('/api/v1/registrations?evento_id=' . $this->event->id)->assertOk();

        foreach ($response->json('data.items') as $item) {
            $this->assertEquals($this->event->id, $item['evento_id']);
        }
    }

    public function test_list_registrations_filter_by_pago_status(): void
    {
        $this->postJson('/api/v1/registrations', $this->validPayload())->assertCreated();

        $response = $this->getJson('/api/v1/registrations?pago_status=pending')->assertOk();
        $this->assertCount(1, $response->json('data.items'));

        $response = $this->getJson('/api/v1/registrations?pago_status=paid')->assertOk();
        $this->assertCount(0, $response->json('data.items'));
    }

    public function test_list_registrations_filter_by_tipo_pago(): void
    {
        $this->postJson('/api/v1/registrations', $this->validPayload())->assertCreated();

        $response = $this->getJson('/api/v1/registrations?tipo_pago=QR')->assertOk();
        $this->assertCount(1, $response->json('data.items'));

        $response = $this->getJson('/api/v1/registrations?tipo_pago=TRANSFERENCIA')->assertOk();
        $this->assertCount(0, $response->json('data.items'));
    }

    // ==========================================
    // 3.3 VER INSCRIPCION
    // ==========================================

    public function test_show_registration_by_reference(): void
    {
        $payload = $this->validPayload();
        $reference = $payload[0]['referencia'];

        $this->postJson('/api/v1/registrations', $payload)->assertCreated();

        $this->getJson('/api/v1/registrations/' . $reference)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.referencia', $reference);
    }

    public function test_show_registration_includes_totals_and_participants(): void
    {
        $payload = $this->validPayload();
        $reference = $payload[0]['referencia'];

        $this->postJson('/api/v1/registrations', $payload)->assertCreated();

        $response = $this->getJson('/api/v1/registrations/' . $reference)->assertOk();

        $this->assertArrayHasKey('totales', $response->json('data'));
        $this->assertNotEmpty($response->json('data.participantes'));
    }

    public function test_show_registration_returns_404_for_nonexistent(): void
    {
        $this->getJson('/api/v1/registrations/FAKE-REF-999')
            ->assertNotFound();
    }

    // ==========================================
    // 3.4 ACTUALIZAR PAGO
    // ==========================================

    public function test_update_payment_to_paid(): void
    {
        $payload = $this->validPayload();
        $reference = $payload[0]['referencia'];

        $this->postJson('/api/v1/registrations', $payload)->assertCreated();

        $this->patchJson('/api/v1/registrations/' . $reference . '/payment', [
            'pago_status' => 'paid',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.pago_status', 'paid');
    }

    public function test_update_payment_to_failed(): void
    {
        $payload = $this->validPayload();
        $reference = $payload[0]['referencia'];

        $this->postJson('/api/v1/registrations', $payload)->assertCreated();

        $this->patchJson('/api/v1/registrations/' . $reference . '/payment', [
            'pago_status' => 'failed',
        ])
            ->assertOk()
            ->assertJsonPath('data.pago_status', 'failed');
    }

    public function test_update_payment_to_cancelled(): void
    {
        $payload = $this->validPayload();
        $reference = $payload[0]['referencia'];

        $this->postJson('/api/v1/registrations', $payload)->assertCreated();

        $this->patchJson('/api/v1/registrations/' . $reference . '/payment', [
            'pago_status' => 'cancelled',
        ])
            ->assertOk()
            ->assertJsonPath('data.pago_status', 'cancelled');
    }

    public function test_update_payment_rejects_invalid_status(): void
    {
        $payload = $this->validPayload();
        $reference = $payload[0]['referencia'];

        $this->postJson('/api/v1/registrations', $payload)->assertCreated();

        $this->patchJson('/api/v1/registrations/' . $reference . '/payment', [
            'pago_status' => 'invalid_status',
        ])
            ->assertUnprocessable();
    }

    public function test_update_payment_returns_404_for_nonexistent_reference(): void
    {
        $this->patchJson('/api/v1/registrations/FAKE-REF/payment', [
            'pago_status' => 'paid',
        ])
            ->assertNotFound();
    }

    // ==========================================
    // 3.5 ELIMINAR INSCRIPCION
    // ==========================================

    public function test_delete_registration_returns_200(): void
    {
        $payload = $this->validPayload();
        $reference = $payload[0]['referencia'];

        $this->postJson('/api/v1/registrations', $payload)->assertCreated();

        $this->deleteJson('/api/v1/registrations/' . $reference)
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_delete_registration_removes_from_database(): void
    {
        $payload = $this->validPayload();
        $reference = $payload[0]['referencia'];

        $this->postJson('/api/v1/registrations', $payload)->assertCreated();

        $this->deleteJson('/api/v1/registrations/' . $reference);

        $this->assertDatabaseMissing('registrations', ['referencia' => $reference]);
    }

    public function test_delete_registration_returns_404_for_nonexistent(): void
    {
        $this->deleteJson('/api/v1/registrations/FAKE-REF')
            ->assertNotFound();
    }

    public function test_full_registration_lifecycle(): void
    {
        $payload = $this->validPayload();
        $reference = $payload[0]['referencia'];

        // Create
        $this->postJson('/api/v1/registrations', $payload)
            ->assertCreated();

        // Read
        $this->getJson('/api/v1/registrations/' . $reference)
            ->assertOk()
            ->assertJsonPath('data.pago_status', 'pending');

        // Update payment
        $this->patchJson('/api/v1/registrations/' . $reference . '/payment', [
            'pago_status' => 'paid',
        ])
            ->assertOk()
            ->assertJsonPath('data.pago_status', 'paid');

        // Verify update
        $this->getJson('/api/v1/registrations/' . $reference)
            ->assertOk()
            ->assertJsonPath('data.pago_status', 'paid');

        // Delete
        $this->deleteJson('/api/v1/registrations/' . $reference)
            ->assertOk();

        // Verify deletion
        $this->getJson('/api/v1/registrations/' . $reference)
            ->assertNotFound();
    }
}

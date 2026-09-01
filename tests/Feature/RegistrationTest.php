<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Evento;
use App\Models\FormType;
use App\Models\Persona;
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
    private Category $categoria;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsPersona();
        $this->event = Evento::factory()->create();
        $this->formType = FormType::factory()->create([
            'event_id' => $this->event->id,
            // Precios por período (12/08/2026) — pinneado a true
            // (FormTypeFactory lo randomiza con faker->boolean()), ver
            // PRD-precios-periodos-fechas.md sección 0.
            'requiere_categoria' => true,
        ]);
        $this->categoria = Category::factory()->create([
            'event_id' => $this->event->id,
            'price' => 100,
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
            'categoria' => $this->categoria->id,
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
                // fee_pct default del evento es 0.05 (5%, ver migración
                // 2026_08_11_150000_add_fee_pct_to_eventos_table) —
                // CrearInscripcionAction::validateFeePct() lo recalcula y
                // rechaza si no coincide, así que el payload de test tiene
                // que traer el fee real, no 0.
                'fee' => 5,
                'descuento' => 0,
                'grand_total' => 105,
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
            'grand_total' => 105,
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

    /**
     * Bug real 25/08/2026 (reportado por el usuario: "muchas personas de
     * sexo femenino registraron masculino") — StoreRegistrationRequest
     * nunca declaró una regla para 'genero', así que $request->validated()
     * lo descartaba en silencio y ParticipantDTO/RegistrationService
     * caían siempre al default 'Masculino', sin importar lo que el
     * participante hubiera elegido. validPayload() ya manda 'Femenino' —
     * antes del fix, este test fallaba (quedaba guardado 'Masculino').
     */
    public function test_create_registration_respeta_el_genero_elegido_no_siempre_masculino(): void
    {
        $this->postJson('/api/v1/registrations', $this->validPayload())->assertCreated();

        $this->assertDatabaseHas('participantes', [
            'numero_documento' => '87654321',
            'genero' => 'Femenino',
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
            'categoria' => $this->categoria->id,
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

    /**
     * `hasPromoCode` pasó de `eventos` a `form_types` (QA visual, 10/08) —
     * este test cubre la validación server-side nueva en
     * RegistrationService::consumePromoCode(): rechazar el código aunque
     * exista y no esté usado, si el form_type de la inscripción no admite
     * promo. `FormType::factory()` no seteaba `has_promo_code` antes de
     * este cambio, así que el `$this->formType` de setUp() ya nace en
     * `false` (default de la migración) sin tocar la factory.
     */
    public function test_create_registration_rejects_promo_code_when_form_type_not_eligible(): void
    {
        \App\Models\PromoCode::factory()->create([
            'event_id'   => $this->event->id,
            'promo_code' => 'NOELEGIBLE',
        ]);

        $payload = $this->validPayload(['promoCodigo' => 'NOELEGIBLE']);

        $this->postJson('/api/v1/registrations', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('error', 'Este tipo de formulario no admite códigos promocionales.');

        $this->assertDatabaseCount('registrations', 0);
    }

    public function test_create_registration_accepts_promo_code_when_form_type_eligible(): void
    {
        $this->formType->update(['has_promo_code' => true]);
        \App\Models\PromoCode::factory()->create([
            'event_id'   => $this->event->id,
            'promo_code' => 'SIELEGIBLE',
        ]);

        $payload = $this->validPayload(['promoCodigo' => 'SIELEGIBLE']);

        $this->postJson('/api/v1/registrations', $payload)
            ->assertCreated()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('promo_codes', ['promo_code' => 'SIELEGIBLE', 'usado' => true]);
    }

    /**
     * Categorías por form_type (27/08/2026) — ver
     * PLAN-CATEGORIAS-POR-FORM-TYPE-27082026.md. Antes de este cambio,
     * `CrearInscripcionAction::validatePrecioCategoria()` solo chequeaba
     * `event_id` — una categoría con `formulario_id` cargado para OTRO
     * form_type del mismo evento se aceptaba igual.
     */
    public function test_create_registration_rejects_category_scoped_to_a_different_form_type(): void
    {
        $otroFormType = FormType::factory()->create([
            'event_id' => $this->event->id,
            'requiere_categoria' => true,
        ]);
        $categoriaDeOtroFormType = Category::factory()->create([
            'event_id' => $this->event->id,
            'formulario_id' => $otroFormType->id,
            'price' => 100,
        ]);

        $payload = $this->validPayload([
            'categoria' => $categoriaDeOtroFormType->id,
            'precioCategoria' => 100,
        ]);

        $this->postJson('/api/v1/registrations', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('error', "La categoría '{$categoriaDeOtroFormType->id}' no es válida para este evento.");

        $this->assertDatabaseCount('registrations', 0);
    }

    /**
     * Sin regresión: una categoría con `formulario_id = null` (default,
     * compartida por todos los form_types del evento) sigue aceptándose
     * igual que antes de este cambio.
     */
    public function test_create_registration_accepts_category_shared_across_form_types(): void
    {
        $this->postJson('/api/v1/registrations', $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('success', true);
    }

    public function test_create_registration_rejects_empty_participants(): void
    {
        $payload = $this->validPayload();
        $payload[0]['participantes'] = [];

        $this->postJson('/api/v1/registrations', $payload)
            ->assertUnprocessable();
    }

    /**
     * Género por catálogo (31/08/2026) — antes esta regla solo pedía
     * 'string', así que un valor fuera del ENUM de participantes.genero
     * (Masculino/Femenino/Otro) pasaba la validación de Laravel y recién
     * explotaba con un 500 crudo de SQL al insertar. Ver
     * PLAN-GENERO-CATALOGO-CAMPOS-OPCIONALES-31082026.md.
     */
    public function test_create_registration_rejects_genero_fuera_del_catalogo(): void
    {
        $payload = $this->validPayload();
        $payload[0]['participantes'][0]['genero'] = 'Klingon';

        $this->postJson('/api/v1/registrations', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['0.participantes.0.genero']);
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

    /**
     * Contacto de emergencia relajado del todo (31/08/2026) — antes se
     * rechazaba con 422 cuando el form_type lo pedía (default true). Ahora
     * nunca es obligatorio a nivel de validación, ver
     * PLAN-GENERO-CATALOGO-CAMPOS-OPCIONALES-31082026.md.
     */
    public function test_create_registration_allows_missing_emergency_contact(): void
    {
        $payload = $this->validPayload();
        unset($payload[0]['participantes'][0]['contacto_emergencia']);

        $this->postJson('/api/v1/registrations', $payload)
            ->assertCreated();
    }

    /**
     * Bug real (01/09/2026, reportado por el usuario: "no se pudo
     * completar el registro, intenta nuevamente" — reproducido con datos
     * reales) — `personas.numero_documento` no tiene constraint único
     * (solo `email`), así que pueden existir 2 cuentas Persona distintas
     * con el mismo documento y emails distintos. Antes,
     * RegistrationService::syncPersonas() podía matchear la cuenta
     * EQUIVOCADA por documento e intentar pisarle el email con uno que
     * ya era de la OTRA cuenta — reventaba el UNIQUE de `personas.email`
     * dentro de la misma transacción que crea la inscripción, así que la
     * inscripción entera se revertía por un problema de una tabla
     * secundaria/derivada. Ahora syncPersonas() nunca debe poder tumbar
     * un registro real.
     */
    public function test_create_registration_no_falla_por_numero_documento_compartido_entre_2_personas(): void
    {
        $payload = $this->validPayload();
        $documento = $payload[0]['participantes'][0]['numeroDocumento'];
        $correo = $payload[0]['participantes'][0]['correo'];

        // Persona A: mismo documento, OTRO email.
        Persona::factory()->create(['numero_documento' => $documento, 'email' => 'otro.email@test.net']);
        // Persona B: dueña real del email que va a llegar en el payload.
        Persona::factory()->create(['numero_documento' => '00000000', 'email' => $correo]);

        $this->postJson('/api/v1/registrations', $payload)
            ->assertCreated();
    }

    /**
     * Caja para eventos tipo congreso (20/08/2026) — con
     * `requiere_contacto_emergencia=false` en el form_type, la
     * inscripción pública se acepta igual sin contacto de emergencia
     * (mismo comportamiento que con el flag en true, ver el test de
     * arriba — el flag ya no cambia nada a nivel de validación, solo
     * controla si el frontend MUESTRA la sección).
     */
    public function test_create_registration_allows_missing_emergency_contact_when_form_type_opts_out(): void
    {
        $this->formType->update(['requiere_contacto_emergencia' => false]);

        $payload = $this->validPayload();
        unset($payload[0]['participantes'][0]['contacto_emergencia']);

        $this->postJson('/api/v1/registrations', $payload)
            ->assertCreated();
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
        $this->assertEquals('105.00', $total->grand_total);
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

        // RegistrationController::store() atrapa el DomainException de
        // referencia duplicada y devuelve un 422 limpio — a propósito,
        // ver el comentario de CrearInscripcionAction::createInTransaction()
        // ("así el controller la atrapa y devuelve un 422 limpio en vez
        // de un 500 sin manejar"). Esta prueba pedía 500, que ya no es
        // ni el comportamiento real ni el deseado.
        $this->postJson('/api/v1/registrations', $payload)
            ->assertStatus(422)
            ->assertJsonPath('success', false);
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
        $formType2 = FormType::factory()->create(['event_id' => $event2->id, 'requiere_categoria' => true]);
        $categoria2 = Category::factory()->create(['event_id' => $event2->id, 'price' => 100]);

        $this->postJson('/api/v1/registrations', $this->validPayload())->assertCreated();

        // form_types_id/categoria propios de event2 — antes esta prueba
        // reusaba los del evento base solo para variar evento_id, pero
        // eso ya no pasa la revalidación de precio (categoría debe
        // pertenecer al mismo evento, ver
        // CrearInscripcionAction::validatePrecioCategoria()).
        $payload2 = $this->validPayload();
        $payload2[0]['evento_id'] = $event2->id;
        $payload2[0]['form_types_id'] = $formType2->id;
        $payload2[0]['participantes'][0]['categoria'] = $categoria2->id;
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

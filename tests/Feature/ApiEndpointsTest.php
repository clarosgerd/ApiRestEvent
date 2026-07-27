<?php

namespace Tests\Feature;

use App\Models\Evento;
use App\Models\FormType;
use App\Models\Persona;
use App\Models\Registration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApiEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_index_and_show_endpoints_work(): void
    {
        Evento::factory()->create();

        $this->getJson('/api/v1/event')
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'eventos',
                'pagination'
            ])
            ->assertJsonPath('success', true);

        $event = Evento::query()->first();

        $this->getJson('/api/v1/event/' . $event->id)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('eventos.id', $event->id);
    }

    public function test_persona_register_login_list_and_show_endpoints_work(): void
    {
        $email = 'juan.' . Str::random(6) . '@example.com';
        $this->actingAsPersona();

        $this->postJson('/api/v1/persona/register', [
            'nombre' => 'Juan',
            'apellido' => 'Pérez',
            'alias' => 'jperez',
            'sexo' => 'Masculino',
            'tipo_documento' => 'DNI',
            'numero_documento' => '12345678',
            'fecha_nacimiento' => '1990-01-01',
            'correo' => $email,
            'direccion' => 'Calle 123',
            'ciudad' => 'Montevideo',
            'telefono' => '22001234',
            'celular' => '099123456',
            'email' => $email,
            'password' => 'secret123',
        ])->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'message', 'data', 'token']);

        $persona = Persona::query()->where('email', $email)->firstOrFail();

        $this->postJson('/api/v1/persona/login', [
            'email' => $email,
            'password' => 'secret123',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'message', 'data' => ['persona', 'token']]);

        $this->getJson('/api/v1/persona')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'persona', 'pagination']);

        $this->getJson('/api/v1/persona/' . $persona->id)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('persona.nombre', 'Juan');
    }

    public function test_persona_logout_returns_unauthorized_without_token(): void
    {
        $this->postJson('/api/v1/persona/logout')
            ->assertStatus(401)
            ->assertJsonPath('message', 'No autorizado');
    }

    public function test_registrations_endpoints_work(): void
    {
        $this->actingAsPersona();
        $event = Evento::factory()->create();
        $formType = FormType::factory()->create(['event_id' => $event->id]);

        $payload = [[
            'referencia' => 'REF-' . Str::random(6),
            'fecha' => now()->toDateTimeString(),
            'evento_id' => $event->id,
            'form_types_id' => $formType->id,
            'evento_nombre' => $event->nombre,
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
            'participantes' => [[
                'nombre' => 'Ana',
                'apellido' => 'García',
                'alias' => 'anita',
                'genero' => 'Femenino',
                'tipoDocumento' => 'DNI',
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
                'ciudad' => 'Montevideo',
                'telefono' => '22001122',
                'categoria' => 1,
                'precioCategoria' => 100,
                'donacion' => 0,
                'promoDescuento' => 0,
                'promoCodigo' => '',
                'subtotal' => 100,
                'contacto_emergencia' => [
                    'nombre' => 'Luis García',
                    'celular' => '099111111',
                    'relacion' => 'Padre',
                ],
                'souvenirs' => [],
            ]],
        ]];

        $createResponse = $this->postJson('/api/v1/registrations', $payload)
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'message', 'data']);

        $reference = $createResponse->json('data.referencia');

        $this->getJson('/api/v1/registrations')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'items' => [[
                        'referencia',
                        'fecha',
                        'evento_id',
                        'evento_nombre',
                        'tipo_pago',
                        'pago_status',
                        'totales',
                        'participantes',
                    ]],
                    'pagination' => ['current_page', 'last_page', 'per_page', 'total'],
                ],
            ]);

        $this->getJson('/api/v1/registrations/' . $reference)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.referencia', $reference);

        $this->patchJson('/api/v1/registrations/' . $reference . '/payment', [
            'pago_status' => 'paid',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.pago_status', 'paid');

        $this->deleteJson('/api/v1/registrations/' . $reference)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('registrations', ['referencia' => $reference]);
    }
}

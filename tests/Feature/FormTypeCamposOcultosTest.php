<?php

namespace Tests\Feature;

use App\Models\Evento;
use App\Models\FormType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ocultar Dirección/Ciudad/Teléfono/Alias por tipo de formulario
 * (01/09/2026) — ver PLAN-OCULTAR-CAMPOS-FORM-TYPE-01092026.md. Pedido
 * del usuario: "deberíamos colocar en form_type quitar esos campos de
 * dirección, ciudad, teléfono, etc. desde admin-eventos".
 */
class FormTypeCamposOcultosTest extends TestCase
{
    use RefreshDatabase;

    public function test_form_type_sin_configurar_expone_campos_ocultos_vacio(): void
    {
        $this->actingAsAdmin();
        $event = Evento::factory()->create();
        $formType = FormType::factory()->create(['event_id' => $event->id]);

        $this->getJson('/api/v1/event/'.$event->id)
            ->assertOk()
            ->assertJsonPath('eventos.formTypes.0.camposOcultos', []);
    }

    public function test_store_form_type_acepta_campos_ocultos(): void
    {
        $this->actingAsAdmin();
        $event = Evento::factory()->create();

        $response = $this->postJson('/api/v1/form-type', [
            'event_id' => $event->id,
            'name' => 'Individual',
            'icon' => '🏃',
            'description' => 'x',
            'cupo_total' => 10,
            'precio_base' => 50,
            'costo_edicion' => 0,
            'tiempo_expiracion_min' => 30,
            'color' => '#00bad2',
            'campos_ocultos' => ['direccion', 'telefono'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('formType.camposOcultos', ['direccion', 'telefono']);

        $formType = FormType::where('event_id', $event->id)->first();
        $this->assertSame(['direccion', 'telefono'], $formType->campos_ocultos);
    }

    public function test_update_form_type_reemplaza_campos_ocultos(): void
    {
        $this->actingAsAdmin();
        $event = Evento::factory()->create();
        $formType = FormType::factory()->create([
            'event_id' => $event->id,
            'campos_ocultos' => ['direccion'],
        ]);

        $this->putJson('/api/v1/form-type/'.$formType->id, ['campos_ocultos' => ['ciudad', 'alias']])
            ->assertOk()
            ->assertJsonPath('formType.camposOcultos', ['ciudad', 'alias']);

        $this->assertSame(['ciudad', 'alias'], $formType->refresh()->campos_ocultos);
    }

    public function test_update_form_type_acepta_ocultar_nacimiento_y_genero(): void
    {
        $this->actingAsAdmin();
        $event = Evento::factory()->create();
        $formType = FormType::factory()->create(['event_id' => $event->id]);

        $this->putJson('/api/v1/form-type/'.$formType->id, ['campos_ocultos' => ['nacimiento', 'genero', 'alias']])
            ->assertOk()
            ->assertJsonPath('formType.camposOcultos', ['nacimiento', 'genero', 'alias']);

        $this->getJson('/api/v1/event/'.$event->id)
            ->assertOk()
            ->assertJsonPath('eventos.formTypes.0.camposOcultos', ['nacimiento', 'genero', 'alias']);
    }

    /**
     * Género/fecha ocultos (26/09/2026): el participante se guarda con
     * neutros ('Otro' / 1900) pero syncPersonas() NO debe pisar el dato real
     * de una Persona que ya existe con ese correo.
     */
    private function inscribir(FormType $formType, string $correo, array $participante = []): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        $categoria = \App\Models\Category::factory()->create(['event_id' => $formType->event_id, 'price' => 50]);

        app(\App\Actions\CrearInscripcionAction::class)->handle(\App\DTOs\RegistrationDTO::fromArray([
            'referencia' => 'LA-TEST-' . uniqid(),
            'fecha' => now()->toDateTimeString(),
            'evento_id' => $formType->event_id,
            'evento_nombre' => 'Evento',
            'form_types_id' => $formType->id,
            'tipo_pago' => 'pendiente',
            'pago_status' => 'pending',
            'pay_order_number' => null,
            'totales' => [
                'inscripcion' => 50, 'donacion' => 0, 'souvenirs' => 0, 'talleres' => 0, 'fee' => 2.5,
                'descuento' => 0, 'descuento_registrante' => 0, 'grand_total' => 52.5,
            ],
            'participantes' => [array_merge([
                'nombre' => 'Ana', 'apellido' => 'Prueba', 'alias' => '', 'genero' => 'Otro',
                'tipoDocumento' => 'CI', 'numeroDocumento' => '40000001',
                'polera' => '', 'precioPolera' => 0,
                'nacimiento' => ['dia' => 1, 'mes' => 1, 'anio' => 1900], 'edad' => 0,
                'correo' => $correo, 'direccion' => '', 'ciudad' => '', 'telefono' => '',
                'contacto_emergencia' => ['nombre' => '', 'celular' => '', 'relacion' => ''],
                'souvenirs' => [], 'answers' => [], 'talleres' => [],
                'categoria' => (string) $categoria->id, 'precioCategoria' => 50,
                'donacion' => 0, 'promoDescuento' => 0, 'promoCodigo' => '', 'subtotal' => 50,
            ], $participante)],
        ]));
    }

    public function test_persona_existente_no_pierde_genero_ni_fecha_si_el_form_type_los_oculta(): void
    {
        $event = Evento::factory()->create();
        $formType = FormType::factory()->create([
            'event_id' => $event->id, 'cupo_total' => 100, 'activo' => true, 'requiere_categoria' => true,
            'campos_ocultos' => ['nacimiento', 'genero'],
        ]);
        \App\Models\Persona::factory()->create([
            'email' => 'real@test.net', 'numero_documento' => '40000001',
            'sexo' => 'Femenino', 'fecha_nacimiento' => '1990-05-17',
        ]);

        $this->inscribir($formType, 'real@test.net');

        $persona = \App\Models\Persona::where('email', 'real@test.net')->firstOrFail();
        $this->assertSame('Femenino', $persona->sexo);
        $this->assertSame('1990-05-17', substr((string) $persona->fecha_nacimiento, 0, 10));
        // El participante de ESTA inscripción sí queda con los neutros.
        $this->assertDatabaseHas('participantes', ['correo' => 'real@test.net', 'genero' => 'Otro', 'edad' => 0]);
    }

    public function test_persona_existente_si_se_actualiza_cuando_el_form_type_no_oculta_genero_ni_fecha(): void
    {
        $event = Evento::factory()->create();
        $formType = FormType::factory()->create([
            'event_id' => $event->id, 'cupo_total' => 100, 'activo' => true, 'requiere_categoria' => true,
        ]);
        \App\Models\Persona::factory()->create([
            'email' => 'real@test.net', 'numero_documento' => '40000001',
            'sexo' => 'Femenino', 'fecha_nacimiento' => '1990-05-17',
        ]);

        $this->inscribir($formType, 'real@test.net', [
            'genero' => 'Masculino', 'nacimiento' => ['dia' => 3, 'mes' => 4, 'anio' => 1985], 'edad' => 41,
        ]);

        $persona = \App\Models\Persona::where('email', 'real@test.net')->firstOrFail();
        $this->assertSame('Masculino', $persona->sexo);
        $this->assertSame('1985-04-03', substr((string) $persona->fecha_nacimiento, 0, 10));
    }

    /** Apellido oculto (empresa expositora, 26/09/2026): el API acepta 'apellido' en campos_ocultos. */
    public function test_form_type_acepta_ocultar_apellido(): void
    {
        $this->actingAsAdmin();
        $event = Evento::factory()->create();
        $formType = FormType::factory()->create(['event_id' => $event->id]);

        $this->putJson('/api/v1/form-type/'.$formType->id, ['campos_ocultos' => ['apellido', 'nacimiento']])
            ->assertOk()
            ->assertJsonPath('formType.camposOcultos', ['apellido', 'nacimiento']);

        $this->getJson('/api/v1/event/'.$event->id)
            ->assertOk()
            ->assertJsonPath('eventos.formTypes.0.camposOcultos', ['apellido', 'nacimiento']);
    }

    public function test_persona_existente_conserva_su_apellido_si_el_form_type_lo_oculta(): void
    {
        $event = Evento::factory()->create();
        $formType = FormType::factory()->create([
            'event_id' => $event->id, 'cupo_total' => 100, 'activo' => true, 'requiere_categoria' => true,
            'campos_ocultos' => ['apellido'],
        ]);
        \App\Models\Persona::factory()->create([
            'email' => 'real@test.net', 'numero_documento' => '40000001', 'apellido' => 'Gonzales',
        ]);

        $this->inscribir($formType, 'real@test.net', ['apellido' => '-']);

        $this->assertSame('Gonzales', \App\Models\Persona::where('email', 'real@test.net')->value('apellido'));
        // El participante de ESTA inscripción sí queda con el neutro.
        $this->assertDatabaseHas('participantes', ['correo' => 'real@test.net', 'apellido' => '-']);
    }

    public function test_persona_existente_si_actualiza_su_apellido_cuando_el_form_type_no_lo_oculta(): void
    {
        $event = Evento::factory()->create();
        $formType = FormType::factory()->create([
            'event_id' => $event->id, 'cupo_total' => 100, 'activo' => true, 'requiere_categoria' => true,
        ]);
        \App\Models\Persona::factory()->create([
            'email' => 'real@test.net', 'numero_documento' => '40000001', 'apellido' => 'Gonzales',
        ]);

        $this->inscribir($formType, 'real@test.net', ['apellido' => 'Perez']);

        $this->assertSame('Perez', \App\Models\Persona::where('email', 'real@test.net')->value('apellido'));
    }

    public function test_persona_nueva_se_crea_con_apellido_neutro_si_el_form_type_lo_oculta(): void
    {
        $event = Evento::factory()->create();
        $formType = FormType::factory()->create([
            'event_id' => $event->id, 'cupo_total' => 100, 'activo' => true, 'requiere_categoria' => true,
            'campos_ocultos' => ['apellido'],
        ]);

        $this->inscribir($formType, 'nueva@test.net', ['apellido' => '-']);

        $this->assertSame('-', \App\Models\Persona::where('email', 'nueva@test.net')->value('apellido'));
    }

    public function test_persona_nueva_se_crea_con_los_neutros_si_el_form_type_los_oculta(): void
    {
        $event = Evento::factory()->create();
        $formType = FormType::factory()->create([
            'event_id' => $event->id, 'cupo_total' => 100, 'activo' => true, 'requiere_categoria' => true,
            'campos_ocultos' => ['nacimiento', 'genero'],
        ]);

        $this->inscribir($formType, 'nueva@test.net');

        $persona = \App\Models\Persona::where('email', 'nueva@test.net')->firstOrFail();
        $this->assertSame('Otro', $persona->sexo);
        $this->assertSame('1900-01-01', substr((string) $persona->fecha_nacimiento, 0, 10));
    }

    public function test_rechaza_un_campo_fuera_del_enum_permitido(): void
    {
        $this->actingAsAdmin();
        $event = Evento::factory()->create();

        $this->postJson('/api/v1/form-type', [
            'event_id' => $event->id,
            'name' => 'Individual',
            'icon' => '🏃',
            'description' => 'x',
            'cupo_total' => 10,
            'precio_base' => 50,
            'costo_edicion' => 0,
            'tiempo_expiracion_min' => 30,
            'color' => '#00bad2',
            'campos_ocultos' => ['correo'], // correo no está permitido, es identidad de la persona
        ])->assertUnprocessable();
    }
}

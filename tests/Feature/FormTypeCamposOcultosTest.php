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

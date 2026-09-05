<?php

namespace Tests\Feature;

use App\Models\Evento;
use App\Models\FormType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CRUD standalone de FormType (`POST`/`PUT /form-type`) para el flag
 * `edicion_solo_extras` (04/09/2026) — mismo patrón de test que
 * FormTypeHasDonationPromoTest. El efecto real del flag (bloquear datos
 * personales/categoría al editar una inscripción) se cubre en
 * EdicionSoloExtrasTest — este archivo solo confirma que el flag se puede
 * crear/leer/actualizar correctamente, incluyendo el gap real que ya
 * existía para `requiere_contacto_emergencia`
 * (FormTypeService::create() no hace mass-assignment).
 */
class FormTypeEdicionSoloExtrasFlagTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_form_type_defaults_to_false_when_omitted(): void
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
        ])->assertCreated()
            ->assertJsonPath('formType.edicionSoloExtras', false);

        $formType = FormType::where('event_id', $event->id)->first();
        $this->assertFalse((bool) $formType->edicion_solo_extras);
    }

    public function test_store_form_type_accepts_edicion_solo_extras(): void
    {
        $this->actingAsAdmin();
        $event = Evento::factory()->create();

        $this->postJson('/api/v1/form-type', [
            'event_id' => $event->id,
            'name' => 'Ponente',
            'icon' => '🎤',
            'description' => 'x',
            'cupo_total' => 10,
            'precio_base' => 0,
            'costo_edicion' => 0,
            'tiempo_expiracion_min' => 30,
            'color' => '#00bad2',
            'edicion_solo_extras' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('formType.edicionSoloExtras', true);

        $formType = FormType::where('event_id', $event->id)->first();
        $this->assertTrue((bool) $formType->edicion_solo_extras);
    }

    public function test_update_form_type_toggles_edicion_solo_extras(): void
    {
        $this->actingAsAdmin();
        $event = Evento::factory()->create();
        $formType = FormType::factory()->create([
            'event_id' => $event->id,
            'edicion_solo_extras' => false,
        ]);

        $this->putJson('/api/v1/form-type/'.$formType->id, ['edicion_solo_extras' => true])
            ->assertOk()
            ->assertJsonPath('formType.edicionSoloExtras', true);

        $this->assertTrue((bool) $formType->refresh()->edicion_solo_extras);

        $this->putJson('/api/v1/form-type/'.$formType->id, ['edicion_solo_extras' => false])
            ->assertOk()
            ->assertJsonPath('formType.edicionSoloExtras', false);

        $this->assertFalse((bool) $formType->refresh()->edicion_solo_extras);
    }
}

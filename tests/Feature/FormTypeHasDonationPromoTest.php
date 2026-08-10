<?php

namespace Tests\Feature;

use App\Models\Evento;
use App\Models\FormType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `hasDonation`/`hasPromoCode` pasaron de `eventos` a `form_types`
 * (QA visual, 10/08/2026) — no existía ningún test para el CRUD standalone
 * de FormType (`POST`/`PUT /form-type`) antes de este archivo, verificado
 * antes de escribirlo.
 */
class FormTypeHasDonationPromoTest extends TestCase
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
        ])->assertCreated();

        $formType = FormType::where('event_id', $event->id)->first();
        $this->assertFalse((bool) $formType->has_donation);
        $this->assertFalse((bool) $formType->has_promo_code);
    }

    public function test_store_form_type_accepts_has_donation_and_has_promo_code(): void
    {
        $this->actingAsAdmin();
        $event = Evento::factory()->create();

        $response = $this->postJson('/api/v1/form-type', [
            'event_id' => $event->id,
            'name' => 'Voluntario',
            'icon' => '🏃',
            'description' => 'x',
            'cupo_total' => 10,
            'precio_base' => 0,
            'costo_edicion' => 0,
            'tiempo_expiracion_min' => 30,
            'color' => '#00bad2',
            'has_donation' => true,
            'has_promo_code' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('formType.hasDonation', true)
            ->assertJsonPath('formType.hasPromoCode', true);

        $formType = FormType::where('event_id', $event->id)->first();
        $this->assertTrue((bool) $formType->has_donation);
        $this->assertTrue((bool) $formType->has_promo_code);
    }

    public function test_update_form_type_toggles_has_donation_and_has_promo_code_independently(): void
    {
        $this->actingAsAdmin();
        $event = Evento::factory()->create();
        $formType = FormType::factory()->create([
            'event_id' => $event->id,
            'has_donation' => false,
            'has_promo_code' => false,
        ]);

        $this->putJson('/api/v1/form-type/'.$formType->id, ['has_donation' => true])
            ->assertOk()
            ->assertJsonPath('formType.hasDonation', true)
            ->assertJsonPath('formType.hasPromoCode', false);

        $this->assertTrue((bool) $formType->refresh()->has_donation);
        $this->assertFalse((bool) $formType->has_promo_code);
    }
}

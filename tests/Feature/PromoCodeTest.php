<?php

namespace Tests\Feature;

use App\Models\Evento;
use App\Models\PromoCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromoCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_promo_code_valid_returns_success(): void
    {
        $event = Evento::factory()->create();
        $promo = PromoCode::factory()->create([
            'event_id' => $event->id,
            'promo_code' => 'DESCUENTO20',
            'price' => 20,
        ]);

        $this->getJson('/api/v1/promo/' . $event->id . '/code/DESCUENTO20')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'data']);
    }

    public function test_promo_code_invalid_returns_error(): void
    {
        $event = Evento::factory()->create();

        $this->getJson('/api/v1/promo/' . $event->id . '/code/NEXISTE')
            ->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['success', 'error']);
    }

    public function test_promo_code_belongs_to_correct_event(): void
    {
        $event1 = Evento::factory()->create();
        $event2 = Evento::factory()->create();

        PromoCode::factory()->create([
            'event_id' => $event1->id,
            'promo_code' => 'EVENT1CODE',
        ]);

        PromoCode::factory()->create([
            'event_id' => $event2->id,
            'promo_code' => 'EVENT2CODE',
        ]);

        $this->getJson('/api/v1/promo/' . $event1->id . '/code/EVENT1CODE')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->getJson('/api/v1/promo/' . $event1->id . '/code/EVENT2CODE')
            ->assertOk()
            ->assertJsonPath('success', false);
    }

    public function test_promo_code_case_sensitive(): void
    {
        $event = Evento::factory()->create();
        PromoCode::factory()->create([
            'event_id' => $event->id,
            'promo_code' => 'DESCUENTO20',
        ]);

        $this->getJson('/api/v1/promo/' . $event->id . '/code/descuento20')
            ->assertOk()
            ->assertJsonPath('success', false);
    }
}

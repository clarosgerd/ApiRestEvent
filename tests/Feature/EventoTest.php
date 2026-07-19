<?php

namespace Tests\Feature;

use App\Models\Evento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventoTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_index_returns_200_with_pagination(): void
    {
        $this->getJson('/api/v1/event')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'eventos',
                'pagination' => [
                    'total', 'per_page', 'current_page', 'last_page',
                ],
            ]);
    }

    public function test_event_index_returns_empty_array_when_no_events(): void
    {
        $response = $this->getJson('/api/v1/event')->assertOk();

        $this->assertEmpty($response->json('eventos'));
        $this->assertEquals(0, $response->json('pagination.total'));
    }

    public function test_event_index_returns_multiple_events(): void
    {
        Evento::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/event')->assertOk();

        $this->assertCount(3, $response->json('eventos'));
        $this->assertEquals(3, $response->json('pagination.total'));
    }

    public function test_event_index_eager_loads_coordinates(): void
    {
        Evento::factory()->create();

        $response = $this->getJson('/api/v1/event')->assertOk();
        $evento = $response->json('eventos')[0];

        $this->assertArrayHasKey('coordinates', $evento);
    }

    public function test_event_index_eager_loads_categories(): void
    {
        Evento::factory()->create();

        $response = $this->getJson('/api/v1/event')->assertOk();
        $evento = $response->json('eventos')[0];

        $this->assertArrayHasKey('categories', $evento);
    }

    public function test_event_show_returns_200_with_event_name(): void
    {
        $event = Evento::factory()->create(['nombre' => 'Carrera 10K']);

        $this->getJson('/api/v1/event/' . $event->id)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('eventos.name', 'Carrera 10K');
    }

    public function test_event_show_returns_404_for_nonexistent(): void
    {
        $this->getJson('/api/v1/event/99999')
            ->assertNotFound();
    }

    public function test_event_show_loads_coordinates(): void
    {
        $event = Evento::factory()->create();

        $response = $this->getJson('/api/v1/event/' . $event->id)->assertOk();
        $evento = $response->json('eventos');

        $this->assertArrayHasKey('coordinates', $evento);
    }

    public function test_event_show_loads_categories(): void
    {
        $event = Evento::factory()->create();

        $response = $this->getJson('/api/v1/event/' . $event->id)->assertOk();
        $evento = $response->json('eventos');

        $this->assertArrayHasKey('categories', $evento);
    }

    public function test_event_show_loads_souvenirs(): void
    {
        $event = Evento::factory()->create();
        $formType = \App\Models\FormType::factory()->create(['event_id' => $event->id]);
        \App\Models\Souvenir::factory()->create(['form_types_id' => $formType->id]);

        $response = $this->getJson('/api/v1/event/' . $event->id)->assertOk();
        $evento = $response->json('eventos');

        $this->assertNotEmpty($evento['formTypes']);
        $this->assertArrayHasKey('souvenirs', $evento['formTypes'][0]);
    }
}

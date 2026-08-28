<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Evento;
use App\Models\FormType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Categorías por form_type (27/08/2026) — ver
 * PLAN-CATEGORIAS-POR-FORM-TYPE-27082026.md. `formulario_id` ya existía en
 * la tabla (nullable) pero no había ningún test de CategoryController
 * antes de este archivo — nada validaba que el form_type de una categoría
 * perteneciera al mismo evento.
 */
class CategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_acepta_formulario_id_del_mismo_evento(): void
    {
        $this->actingAsAdmin();
        $evento = Evento::factory()->create();
        $formType = FormType::factory()->create(['event_id' => $evento->id]);

        $this->postJson('/api/v1/category', [
            'event_id'      => $evento->id,
            'name'          => 'Elite',
            'price'         => 150,
            'formulario_id' => $formType->id,
        ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('category.formulario_id', $formType->id);

        $this->assertDatabaseHas('categories', [
            'event_id'      => $evento->id,
            'formulario_id' => $formType->id,
        ]);
    }

    public function test_store_rechaza_formulario_id_de_otro_evento(): void
    {
        $this->actingAsAdmin();
        $evento = Evento::factory()->create();
        $otroEvento = Evento::factory()->create();
        $formTypeAjeno = FormType::factory()->create(['event_id' => $otroEvento->id]);

        $this->postJson('/api/v1/category', [
            'event_id'      => $evento->id,
            'name'          => 'Elite',
            'price'         => 150,
            'formulario_id' => $formTypeAjeno->id,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error', 'El tipo de formulario indicado no pertenece a este evento.');

        $this->assertDatabaseCount('categories', 0);
    }

    public function test_store_sin_formulario_id_sigue_funcionando_igual_que_antes(): void
    {
        $this->actingAsAdmin();
        $evento = Evento::factory()->create();

        $this->postJson('/api/v1/category', [
            'event_id' => $evento->id,
            'name'     => 'General',
            'price'    => 50,
        ])
            ->assertCreated()
            ->assertJsonPath('category.formulario_id', null);
    }

    public function test_update_rechaza_formulario_id_de_otro_evento(): void
    {
        $this->actingAsAdmin();
        $evento = Evento::factory()->create();
        $otroEvento = Evento::factory()->create();
        $formTypeAjeno = FormType::factory()->create(['event_id' => $otroEvento->id]);
        $categoria = Category::factory()->create(['event_id' => $evento->id]);

        $this->putJson("/api/v1/category/{$categoria->id}", [
            'formulario_id' => $formTypeAjeno->id,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error', 'El tipo de formulario indicado no pertenece a este evento.');

        $this->assertDatabaseHas('categories', ['id' => $categoria->id, 'formulario_id' => null]);
    }

    public function test_update_acepta_formulario_id_del_mismo_evento(): void
    {
        $this->actingAsAdmin();
        $evento = Evento::factory()->create();
        $formType = FormType::factory()->create(['event_id' => $evento->id]);
        $categoria = Category::factory()->create(['event_id' => $evento->id]);

        $this->putJson("/api/v1/category/{$categoria->id}", [
            'formulario_id' => $formType->id,
        ])
            ->assertOk()
            ->assertJsonPath('category.formulario_id', $formType->id);
    }
}

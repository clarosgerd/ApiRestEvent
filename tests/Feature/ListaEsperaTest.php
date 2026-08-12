<?php

namespace Tests\Feature;

use App\Models\Ciudad;
use App\Models\Evento;
use App\Models\FormType;
use App\Models\ItemStock;
use App\Models\Organizador;
use App\Models\Pais;
use App\Models\Souvenir;
use App\Models\SubtipoEvento;
use App\Models\TipoEvento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Anotarse a la lista de espera — ver ListaEsperaController y
 * PRD-kit-tallas-stock-lista-espera.md. `store()` es público (sin
 * auth); `index()` es del panel (mismo scoping que el resto).
 */
class ListaEsperaTest extends TestCase
{
    use RefreshDatabase;

    private Evento $evento;

    private FormType $formType;

    protected function setUp(): void
    {
        parent::setUp();

        $pais = Pais::factory()->create();
        $ciudad = Ciudad::factory()->create(['pais_id' => $pais->id]);
        $organizador = Organizador::factory()->create();
        $tipoEvento = TipoEvento::factory()->create();
        $subtipoEvento = SubtipoEvento::factory()->create(['tipo_evento_id' => $tipoEvento->id]);

        $this->evento = Evento::factory()->create([
            'organizador_id' => $organizador->id,
            'tipo_evento_id' => $tipoEvento->id,
            'subtipo_evento_id' => $subtipoEvento->id,
            'pais_id' => $pais->id,
            'ciudad_id' => $ciudad->id,
        ]);

        $this->formType = FormType::factory()->create([
            'event_id' => $this->evento->id,
            'cupo_total' => 10,
            'activo' => true,
            'permite_lista_espera' => true,
        ]);
    }

    public function test_rechaza_anotarse_si_todavia_hay_cupo(): void
    {
        $this->postJson("/api/v1/event/{$this->evento->id}/lista-espera", [
            'form_types_id' => $this->formType->id,
            'nombre' => 'Ana', 'correo' => 'ana@test.net',
        ])->assertStatus(422)->assertJsonFragment(['error' => 'Todavía hay cupo disponible — podés inscribirte directamente.']);
    }

    public function test_permite_anotarse_si_el_form_type_esta_lleno(): void
    {
        $this->formType->update(['activo' => false]);

        $response = $this->postJson("/api/v1/event/{$this->evento->id}/lista-espera", [
            'form_types_id' => $this->formType->id,
            'nombre' => 'Ana', 'correo' => 'ana@test.net',
        ]);

        $response->assertStatus(201)->assertJson(['success' => true]);
        $this->assertDatabaseHas('lista_espera', [
            'form_types_id' => $this->formType->id,
            'correo' => 'ana@test.net',
            'estado' => 'pendiente',
        ]);
    }

    public function test_rechaza_anotarse_si_el_form_type_no_admite_lista_de_espera(): void
    {
        $this->formType->update(['activo' => false, 'permite_lista_espera' => false]);

        $this->postJson("/api/v1/event/{$this->evento->id}/lista-espera", [
            'form_types_id' => $this->formType->id,
            'nombre' => 'Ana', 'correo' => 'ana@test.net',
        ])->assertStatus(422);
    }

    public function test_permite_anotarse_a_una_talla_agotada_puntual(): void
    {
        $souvenir = Souvenir::factory()->create(['form_types_id' => $this->formType->id, 'requiere_talla' => true]);
        ItemStock::create(['souvenir_id' => $souvenir->id, 'talla' => 'M', 'sexo' => null, 'cantidad_total' => 0]);

        $this->postJson("/api/v1/event/{$this->evento->id}/lista-espera", [
            'form_types_id' => $this->formType->id,
            'souvenir_id' => $souvenir->id,
            'talla' => 'M',
            'nombre' => 'Ana', 'correo' => 'ana@test.net',
        ])->assertStatus(201);
    }

    public function test_rechaza_anotarse_a_una_talla_con_stock_disponible(): void
    {
        $souvenir = Souvenir::factory()->create(['form_types_id' => $this->formType->id, 'requiere_talla' => true]);
        ItemStock::create(['souvenir_id' => $souvenir->id, 'talla' => 'M', 'sexo' => null, 'cantidad_total' => 5]);

        $this->postJson("/api/v1/event/{$this->evento->id}/lista-espera", [
            'form_types_id' => $this->formType->id,
            'souvenir_id' => $souvenir->id,
            'talla' => 'M',
            'nombre' => 'Ana', 'correo' => 'ana@test.net',
        ])->assertStatus(422);
    }

    public function test_index_requiere_scoping_de_admin(): void
    {
        $otroEvento = Evento::factory()->create([
            'organizador_id' => $this->evento->organizador_id,
            'tipo_evento_id' => $this->evento->tipo_evento_id,
            'subtipo_evento_id' => $this->evento->subtipo_evento_id,
            'pais_id' => $this->evento->pais_id,
            'ciudad_id' => $this->evento->ciudad_id,
        ]);
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'admin', 'evento_id' => $otroEvento->id]);

        $this->getJson("/api/v1/event/{$this->evento->id}/lista-espera")->assertStatus(403);
    }

    public function test_index_lista_las_anotaciones_del_evento(): void
    {
        $this->formType->update(['activo' => false]);
        $this->postJson("/api/v1/event/{$this->evento->id}/lista-espera", [
            'form_types_id' => $this->formType->id,
            'nombre' => 'Ana', 'correo' => 'ana@test.net',
        ]);

        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $this->getJson("/api/v1/event/{$this->evento->id}/lista-espera")
            ->assertStatus(200)
            ->assertJsonFragment(['correo' => 'ana@test.net']);
    }
}

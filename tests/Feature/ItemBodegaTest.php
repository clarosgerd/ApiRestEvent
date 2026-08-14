<?php

namespace Tests\Feature;

use App\Models\Ciudad;
use App\Models\Evento;
use App\Models\FormType;
use App\Models\ItemBodega;
use App\Models\ItemStock;
use App\Models\Organizador;
use App\Models\Pais;
use App\Models\Souvenir;
use App\Models\SubtipoEvento;
use App\Models\TipoEvento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bodega de stock por evento — ver PLAN-BODEGA-STOCK-EVENTO-14082026.md.
 * CRUD scoped al evento (mismo patrón que sesiones de congreso/staff) +
 * asignación a form_type (cupos separados, no un pool compartido).
 */
class ItemBodegaTest extends TestCase
{
    use RefreshDatabase;

    private Evento $evento;

    private FormType $formType5k;

    private FormType $formType10k;

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

        $this->formType5k = FormType::factory()->create(['event_id' => $this->evento->id]);
        $this->formType10k = FormType::factory()->create(['event_id' => $this->evento->id]);
    }

    public function test_admin_scoped_a_su_evento_puede_crear_item_de_bodega(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'admin', 'evento_id' => $this->evento->id]);

        $response = $this->postJson("/api/v1/event/{$this->evento->id}/item-bodega", [
            'nombre' => 'Medalla Finisher',
            'icon' => '🏅',
            'requiere_talla' => false,
            'requiere_sexo' => false,
        ]);

        $response->assertStatus(201)->assertJson(['success' => true]);
        $this->assertDatabaseHas('item_bodega', ['nombre' => 'Medalla Finisher', 'evento_id' => $this->evento->id]);
    }

    public function test_admin_de_otro_evento_no_puede_crear_item_de_bodega(): void
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

        $this->postJson("/api/v1/event/{$this->evento->id}/item-bodega", ['nombre' => 'X'])
            ->assertStatus(403);
    }

    public function test_asignar_crea_souvenir_con_los_campos_copiados(): void
    {
        $item = ItemBodega::create([
            'evento_id' => $this->evento->id,
            'nombre' => 'Medalla Finisher',
            'icon' => '🏅',
            'foto_url' => null,
            'requiere_talla' => false,
            'requiere_sexo' => false,
        ]);

        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $response = $this->postJson("/api/v1/item-bodega/{$item->id}/asignar", [
            'form_types_id' => $this->formType5k->id,
        ]);

        $response->assertStatus(201)->assertJson(['success' => true]);
        $this->assertDatabaseHas('souvenirs', [
            'item_bodega_id' => $item->id,
            'form_types_id' => $this->formType5k->id,
            'name' => 'Medalla Finisher',
            'icon' => '🏅',
            'price' => 0,
            'incluido' => 0,
        ]);
    }

    public function test_asignar_rechaza_form_type_de_otro_evento(): void
    {
        $item = ItemBodega::create(['evento_id' => $this->evento->id, 'nombre' => 'Medalla']);

        $otroEvento = Evento::factory()->create([
            'organizador_id' => $this->evento->organizador_id,
            'tipo_evento_id' => $this->evento->tipo_evento_id,
            'subtipo_evento_id' => $this->evento->subtipo_evento_id,
            'pais_id' => $this->evento->pais_id,
            'ciudad_id' => $this->evento->ciudad_id,
        ]);
        $formTypeOtroEvento = FormType::factory()->create(['event_id' => $otroEvento->id]);

        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $this->postJson("/api/v1/item-bodega/{$item->id}/asignar", [
            'form_types_id' => $formTypeOtroEvento->id,
        ])->assertStatus(422);
    }

    public function test_asignar_dos_veces_al_mismo_form_type_rechaza(): void
    {
        $item = ItemBodega::create(['evento_id' => $this->evento->id, 'nombre' => 'Medalla']);
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $this->postJson("/api/v1/item-bodega/{$item->id}/asignar", ['form_types_id' => $this->formType5k->id])
            ->assertStatus(201);

        $this->postJson("/api/v1/item-bodega/{$item->id}/asignar", ['form_types_id' => $this->formType5k->id])
            ->assertStatus(422);
    }

    public function test_un_item_puede_asignarse_a_varios_form_types_con_cupos_independientes(): void
    {
        $item = ItemBodega::create(['evento_id' => $this->evento->id, 'nombre' => 'Medalla']);
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $souvenir5k = Souvenir::where('id', $this->postJson("/api/v1/item-bodega/{$item->id}/asignar", [
            'form_types_id' => $this->formType5k->id,
        ])->json('souvenir.id'))->first();

        $souvenir10k = Souvenir::where('id', $this->postJson("/api/v1/item-bodega/{$item->id}/asignar", [
            'form_types_id' => $this->formType10k->id,
        ])->json('souvenir.id'))->first();

        // Cupos separados: 1 unidad para 5k, 2 para 10k — independientes.
        ItemStock::create(['souvenir_id' => $souvenir5k->id, 'talla' => null, 'sexo' => null, 'cantidad_total' => 1]);
        ItemStock::create(['souvenir_id' => $souvenir10k->id, 'talla' => null, 'sexo' => null, 'cantidad_total' => 2]);

        $resumen = $this->getJson("/api/v1/event/{$this->evento->id}/item-bodega")->json('item_bodega.0.asignaciones');

        $this->assertCount(2, $resumen);
        $cupos = collect($resumen)->pluck('cupo_total', 'form_types_id');
        $this->assertSame(1, $cupos[$this->formType5k->id]);
        $this->assertSame(2, $cupos[$this->formType10k->id]);
    }

    public function test_borrar_item_de_bodega_no_borra_las_asignaciones(): void
    {
        $item = ItemBodega::create(['evento_id' => $this->evento->id, 'nombre' => 'Medalla']);
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $souvenirId = $this->postJson("/api/v1/item-bodega/{$item->id}/asignar", [
            'form_types_id' => $this->formType5k->id,
        ])->json('souvenir.id');

        $this->deleteJson("/api/v1/item-bodega/{$item->id}")->assertStatus(200);

        $this->assertDatabaseHas('souvenirs', ['id' => $souvenirId, 'item_bodega_id' => null]);
    }
}

<?php

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use App\Models\Evento;
use App\Models\FormType;
use App\Models\ItemBodega;
use App\Models\ItemStock;
use App\Models\Souvenir;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Consolidación monolito (21/08/2026), Fase 1e-iii — Bodega de stock,
 * stock por talla/sexo, lista de espera (solo lectura) y mapa de delivery
 * (solo lectura). Mismo criterio de verificación que fases anteriores:
 * solo el wiring del panel, no la lógica de negocio.
 */
class BodegaListaEsperaDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private Evento $evento;
    private FormType $formType;
    private array $adminSession;

    protected function setUp(): void
    {
        parent::setUp();

        $this->evento = Evento::factory()->create();
        $this->formType = FormType::factory()->create(['event_id' => $this->evento->id, 'activo' => true]);

        $admin = AdminUser::create([
            'nombre' => 'Admin scoped', 'email' => 'admin@example.net',
            'password' => Hash::make('secret123'), 'rol' => 'admin',
            'activo' => true, 'evento_id' => $this->evento->id,
        ]);
        $this->adminSession = [
            'admin_token' => $admin->createToken('t')->plainTextToken,
            'admin_user' => ['id' => $admin->id, 'rol' => 'admin', 'evento_id' => $this->evento->id],
        ];
    }

    public function test_bodega_index_store_update_asignar_destroy(): void
    {
        $this->withSession($this->adminSession)
            ->get("/admin/eventos/{$this->evento->id}/bodega")
            ->assertOk();

        $this->withSession($this->adminSession)
            ->post("/admin/eventos/{$this->evento->id}/bodega", [
                'nombre' => 'Medalla finisher',
                'requiere_talla' => false,
                'requiere_sexo' => false,
            ])
            ->assertRedirect(route('admin.bodega.index', $this->evento->id));

        $item = ItemBodega::where('evento_id', $this->evento->id)->firstOrFail();
        $this->assertSame('Medalla finisher', $item->nombre);

        $this->withSession($this->adminSession)
            ->put("/admin/eventos/{$this->evento->id}/bodega/{$item->id}", [
                'nombre' => 'Medalla finisher grabada',
            ])
            ->assertRedirect(route('admin.bodega.index', $this->evento->id));

        $this->assertDatabaseHas('item_bodega', ['id' => $item->id, 'nombre' => 'Medalla finisher grabada']);

        $this->withSession($this->adminSession)
            ->post("/admin/eventos/{$this->evento->id}/bodega/{$item->id}/asignar", [
                'form_types_id' => $this->formType->id,
            ])
            ->assertRedirect(route('admin.eventos.edit', $this->evento->id) . '#tipos');

        $this->assertDatabaseHas('souvenirs', ['item_bodega_id' => $item->id, 'form_types_id' => $this->formType->id]);

        $this->withSession($this->adminSession)
            ->delete("/admin/eventos/{$this->evento->id}/bodega/{$item->id}")
            ->assertRedirect(route('admin.bodega.index', $this->evento->id));

        $this->assertDatabaseMissing('item_bodega', ['id' => $item->id]);
    }

    public function test_souvenir_stock_index_store_update_destroy(): void
    {
        $souvenir = Souvenir::factory()->create(['form_types_id' => $this->formType->id, 'requiere_talla' => true, 'requiere_sexo' => true]);

        $this->withSession($this->adminSession)
            ->get("/admin/souvenirs/{$souvenir->id}/stock?evento_id={$this->evento->id}&nombre=Polera")
            ->assertOk();

        $this->withSession($this->adminSession)
            ->post("/admin/souvenirs/{$souvenir->id}/stock", [
                'talla' => 'M',
                'sexo' => 'unisex',
                'cantidad_total' => 50,
            ])
            ->assertRedirect();

        $stock = ItemStock::where('souvenir_id', $souvenir->id)->firstOrFail();
        $this->assertSame(50, $stock->cantidad_total);

        $this->withSession($this->adminSession)
            ->put("/admin/item-stock/{$stock->id}", [
                'souvenir_id' => $souvenir->id,
                'cantidad_total' => 80,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('item_stock', ['id' => $stock->id, 'cantidad_total' => 80]);

        $this->withSession($this->adminSession)
            ->delete("/admin/item-stock/{$stock->id}", ['souvenir_id' => $souvenir->id])
            ->assertRedirect();

        $this->assertDatabaseMissing('item_stock', ['id' => $stock->id]);
    }

    public function test_lista_espera_index_renderiza(): void
    {
        $this->withSession($this->adminSession)
            ->get("/admin/eventos/{$this->evento->id}/lista-espera")
            ->assertOk();
    }

    public function test_delivery_index_renderiza(): void
    {
        $this->withSession($this->adminSession)
            ->get("/admin/eventos/{$this->evento->id}/delivery")
            ->assertOk();
    }

    public function test_admin_de_otro_evento_no_puede_ver_bodega_ni_lista_espera_ni_delivery(): void
    {
        $otroEvento = Evento::factory()->create();

        $this->withSession($this->adminSession)->get("/admin/eventos/{$otroEvento->id}/bodega")->assertForbidden();
        $this->withSession($this->adminSession)->get("/admin/eventos/{$otroEvento->id}/lista-espera")->assertForbidden();
        $this->withSession($this->adminSession)->get("/admin/eventos/{$otroEvento->id}/delivery")->assertForbidden();
    }
}

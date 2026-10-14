<?php

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use App\Models\Category;
use App\Models\Evento;
use App\Models\Taller;
use App\Models\TallerCombo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Combos de talleres (12/10/2026, pedido del usuario), capa Admin\* —
 * equivalente in-process al TallerComboControllerTest de admin-eventos
 * (que mockea Http::fake porque ahí es un proxy HTTP; acá
 * Admin\TallerComboController llama directo a TallerComboController de la
 * API, así que se verifica el efecto real en BD).
 */
class TallerComboControllerTest extends TestCase
{
    use RefreshDatabase;

    private array $superSession;

    private Evento $evento;

    private Taller $tallerA;

    private Taller $tallerB;

    protected function setUp(): void
    {
        parent::setUp();

        $super = AdminUser::create([
            'nombre' => 'Super', 'email' => 'super@example.net',
            'password' => Hash::make('secret123'), 'rol' => 'super_admin',
            'activo' => true, 'evento_id' => null,
        ]);
        $this->superSession = [
            'admin_token' => $super->createToken('t')->plainTextToken,
            'admin_user' => ['id' => $super->id, 'rol' => 'super_admin'],
        ];

        $this->evento = Evento::factory()->create();
        $this->tallerA = Taller::factory()->create(['evento_id' => $this->evento->id, 'nombre' => 'Taller A']);
        $this->tallerB = Taller::factory()->create(['evento_id' => $this->evento->id, 'nombre' => 'Taller B']);
    }

    public function test_index_lista_combos_del_evento(): void
    {
        $combo = TallerCombo::create(['evento_id' => $this->evento->id, 'nombre' => '2x1 A+B', 'activo' => true]);
        $combo->talleres()->sync([$this->tallerA->id, $this->tallerB->id]);

        $response = $this->withSession($this->superSession)->get("/admin/eventos/{$this->evento->id}/taller-combos");

        $response->assertOk();
        $response->assertSee('2x1 A+B');
        $response->assertSee('Taller A');
    }

    public function test_store_crea_el_combo_con_sus_talleres(): void
    {
        $response = $this->withSession($this->superSession)->post("/admin/eventos/{$this->evento->id}/taller-combos", [
            'nombre' => 'Combo nuevo',
            'taller_ids' => [$this->tallerA->id, $this->tallerB->id],
            'activo' => '1',
        ]);

        $response->assertRedirect(route('admin.taller-combos.index', $this->evento->id));
        $this->assertDatabaseHas('taller_combos', ['evento_id' => $this->evento->id, 'nombre' => 'Combo nuevo']);
        $combo = TallerCombo::where('nombre', 'Combo nuevo')->firstOrFail();
        $this->assertEqualsCanonicalizing([$this->tallerA->id, $this->tallerB->id], $combo->talleres->pluck('id')->all());
    }

    public function test_store_rechaza_taller_de_otro_evento(): void
    {
        $otroEvento = Evento::factory()->create();
        $tallerAjeno = Taller::factory()->create(['evento_id' => $otroEvento->id]);

        $response = $this->withSession($this->superSession)->post("/admin/eventos/{$this->evento->id}/taller-combos", [
            'nombre' => 'Combo inválido',
            'taller_ids' => [$this->tallerA->id, $tallerAjeno->id],
        ]);

        $response->assertSessionHasErrors();
        $this->assertDatabaseMissing('taller_combos', ['nombre' => 'Combo inválido']);
    }

    public function test_update_persiste_cambios(): void
    {
        $combo = TallerCombo::create(['evento_id' => $this->evento->id, 'nombre' => 'Original', 'activo' => true]);
        $combo->talleres()->sync([$this->tallerA->id, $this->tallerB->id]);

        $this->withSession($this->superSession)->put("/admin/eventos/{$this->evento->id}/taller-combos/{$combo->id}", [
            'nombre' => 'Renombrado',
            'taller_ids' => [$this->tallerA->id, $this->tallerB->id],
            'activo' => '0',
        ]);

        $combo->refresh();
        $this->assertSame('Renombrado', $combo->nombre);
        $this->assertFalse((bool) $combo->activo);
    }

    public function test_destroy_elimina_el_combo(): void
    {
        $combo = TallerCombo::create(['evento_id' => $this->evento->id, 'nombre' => 'A borrar', 'activo' => true]);
        $combo->talleres()->sync([$this->tallerA->id, $this->tallerB->id]);

        $this->withSession($this->superSession)->delete("/admin/eventos/{$this->evento->id}/taller-combos/{$combo->id}");

        $this->assertDatabaseMissing('taller_combos', ['id' => $combo->id]);
    }

    public function test_store_precio_del_combo_por_categoria(): void
    {
        $combo = TallerCombo::create(['evento_id' => $this->evento->id, 'nombre' => 'Combo', 'activo' => true]);
        $combo->talleres()->sync([$this->tallerA->id, $this->tallerB->id]);
        $categoria = Category::factory()->create(['event_id' => $this->evento->id]);

        $response = $this->withSession($this->superSession)->post(
            "/admin/eventos/{$this->evento->id}/taller-combos/{$combo->id}/precios",
            ['category_id' => $categoria->id, 'precio' => 150]
        );

        $response->assertRedirect(route('admin.taller-combos.index', $this->evento->id));
        $this->assertDatabaseHas('taller_combo_precios', [
            'taller_combo_id' => $combo->id, 'category_id' => $categoria->id, 'precio' => 150,
        ]);
    }
}

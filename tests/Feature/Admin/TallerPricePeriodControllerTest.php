<?php

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use App\Models\Category;
use App\Models\Evento;
use App\Models\Taller;
use App\Models\TallerPricePeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Precios por período de un taller (12/10/2026, pedido del usuario), capa
 * Admin\* — equivalente in-process al TallerPricePeriodControllerTest de
 * admin-eventos.
 */
class TallerPricePeriodControllerTest extends TestCase
{
    use RefreshDatabase;

    private array $superSession;

    private Evento $evento;

    private Taller $taller;

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
        $this->taller = Taller::factory()->create(['evento_id' => $this->evento->id, 'nombre' => 'Taller X', 'precio' => 100]);
    }

    public function test_index_muestra_el_precio_vigente_y_los_periodos(): void
    {
        TallerPricePeriod::create([
            'taller_id' => $this->taller->id, 'nombre' => 'Preventa',
            'price' => 80, 'fecha_desde' => now()->subDay()->toDateString(), 'fecha_hasta' => now()->addDay()->toDateString(),
        ]);

        $response = $this->withSession($this->superSession)->get("/admin/eventos/{$this->evento->id}/talleres/{$this->taller->id}/periodos");

        $response->assertOk();
        $response->assertSee('Preventa');
        $response->assertSee('80');
    }

    public function test_store_crea_un_periodo(): void
    {
        $response = $this->withSession($this->superSession)->post(
            "/admin/eventos/{$this->evento->id}/talleres/{$this->taller->id}/periodos",
            [
                'nombre' => 'Early bird', 'price' => 70,
                'fecha_desde' => now()->toDateString(), 'fecha_hasta' => now()->addDays(10)->toDateString(),
            ]
        );

        $response->assertRedirect(route('admin.talleres.periodos.index', [$this->evento->id, $this->taller->id]));
        $this->assertDatabaseHas('taller_price_periods', ['taller_id' => $this->taller->id, 'nombre' => 'Early bird', 'price' => 70]);
    }

    public function test_store_con_categoria_especifica(): void
    {
        $categoria = Category::factory()->create(['event_id' => $this->evento->id]);

        $this->withSession($this->superSession)->post(
            "/admin/eventos/{$this->evento->id}/talleres/{$this->taller->id}/periodos",
            [
                'nombre' => 'VIP', 'category_id' => $categoria->id, 'price' => 200,
                'fecha_desde' => now()->toDateString(), 'fecha_hasta' => now()->addDays(10)->toDateString(),
            ]
        );

        $this->assertDatabaseHas('taller_price_periods', ['taller_id' => $this->taller->id, 'category_id' => $categoria->id, 'price' => 200]);
    }

    public function test_update_persiste_cambios(): void
    {
        $periodo = TallerPricePeriod::create([
            'taller_id' => $this->taller->id, 'nombre' => 'Original',
            'price' => 50, 'fecha_desde' => now()->toDateString(), 'fecha_hasta' => now()->addDays(5)->toDateString(),
        ]);

        $this->withSession($this->superSession)->put(
            "/admin/eventos/{$this->evento->id}/talleres/{$this->taller->id}/periodos/{$periodo->id}",
            [
                'nombre' => 'Renombrado', 'price' => 60,
                'fecha_desde' => now()->toDateString(), 'fecha_hasta' => now()->addDays(5)->toDateString(),
            ]
        );

        $periodo->refresh();
        $this->assertSame('Renombrado', $periodo->nombre);
        $this->assertSame(60.0, (float) $periodo->price);
    }

    public function test_destroy_elimina_el_periodo(): void
    {
        $periodo = TallerPricePeriod::create([
            'taller_id' => $this->taller->id, 'nombre' => 'A borrar',
            'price' => 50, 'fecha_desde' => now()->toDateString(), 'fecha_hasta' => now()->addDays(5)->toDateString(),
        ]);

        $this->withSession($this->superSession)->delete(
            "/admin/eventos/{$this->evento->id}/talleres/{$this->taller->id}/periodos/{$periodo->id}"
        );

        $this->assertDatabaseMissing('taller_price_periods', ['id' => $periodo->id]);
    }
}

<?php

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use App\Models\Evento;
use App\Models\PresupuestoCategoria;
use App\Models\PresupuestoEvento;
use App\Models\Socio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Consolidación monolito (21/08/2026), Fase 1e-iv — Presupuesto de un
 * evento, Liquidación de utilidades y Socios (config global). Mismo
 * criterio de verificación que fases anteriores: solo el wiring del
 * panel, no la lógica de negocio (ya cubierta en
 * ApiRestEvent/tests/Feature/*).
 */
class PresupuestoLiquidacionSociosTest extends TestCase
{
    use RefreshDatabase;

    private Evento $evento;
    private array $superSession;
    private array $adminSession;

    protected function setUp(): void
    {
        parent::setUp();

        $this->evento = Evento::factory()->create();

        $super = AdminUser::create([
            'nombre' => 'Super', 'email' => 'super@example.net',
            'password' => Hash::make('secret123'), 'rol' => 'super_admin',
            'activo' => true, 'evento_id' => null,
        ]);
        $this->superSession = [
            'admin_token' => $super->createToken('t')->plainTextToken,
            'admin_user' => ['id' => $super->id, 'rol' => 'super_admin'],
        ];

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

    public function test_presupuesto_index_store_update_destroy_por_admin_scoped(): void
    {
        $categoria = PresupuestoCategoria::create(['nombre' => 'Auspicios', 'tipo' => 'ingreso', 'activo' => true]);

        $this->withSession($this->adminSession)
            ->get("/admin/eventos/{$this->evento->id}/presupuesto")
            ->assertOk();

        $this->withSession($this->adminSession)
            ->post("/admin/eventos/{$this->evento->id}/presupuesto", [
                'presupuesto_categoria_id' => $categoria->id,
                'tipo' => 'ingreso',
                'monto' => 1000,
                'fecha' => '2027-01-01',
            ])
            ->assertRedirect(route('admin.presupuesto.index', $this->evento->id));

        $movimiento = PresupuestoEvento::where('evento_id', $this->evento->id)->firstOrFail();
        $this->assertSame(1000.0, (float) $movimiento->monto);

        $this->withSession($this->adminSession)
            ->put("/admin/eventos/{$this->evento->id}/presupuesto/{$movimiento->id}", [
                'presupuesto_categoria_id' => $categoria->id,
                'tipo' => 'ingreso',
                'monto' => 1500,
                'fecha' => '2027-01-01',
            ])
            ->assertRedirect(route('admin.presupuesto.index', $this->evento->id));

        $this->assertDatabaseHas('presupuesto_evento', ['id' => $movimiento->id, 'monto' => 1500]);

        $this->withSession($this->adminSession)
            ->delete("/admin/eventos/{$this->evento->id}/presupuesto/{$movimiento->id}")
            ->assertRedirect(route('admin.presupuesto.index', $this->evento->id));

        $this->assertDatabaseMissing('presupuesto_evento', ['id' => $movimiento->id]);
    }

    public function test_admin_de_otro_evento_no_ve_presupuesto(): void
    {
        $otroEvento = Evento::factory()->create();

        $this->withSession($this->adminSession)
            ->get("/admin/eventos/{$otroEvento->id}/presupuesto")
            ->assertForbidden();
    }

    public function test_super_admin_gestiona_categorias_de_presupuesto(): void
    {
        $this->withSession($this->superSession)
            ->post('/admin/presupuesto-categorias', ['nombre' => 'Patrocinios', 'tipo' => 'ingreso', 'activo' => '1'])
            ->assertRedirect(route('admin.presupuesto-categorias.index'));

        $categoria = PresupuestoCategoria::where('nombre', 'Patrocinios')->firstOrFail();

        $this->withSession($this->superSession)
            ->put("/admin/presupuesto-categorias/{$categoria->id}", ['nombre' => 'Patrocinios y auspicios', 'tipo' => 'ingreso', 'activo' => '1'])
            ->assertRedirect(route('admin.presupuesto-categorias.index'));

        $this->assertDatabaseHas('presupuesto_categorias', ['id' => $categoria->id, 'nombre' => 'Patrocinios y auspicios']);

        $this->withSession($this->superSession)
            ->delete("/admin/presupuesto-categorias/{$categoria->id}")
            ->assertRedirect(route('admin.presupuesto-categorias.index'));

        $this->assertDatabaseMissing('presupuesto_categorias', ['id' => $categoria->id]);
    }

    public function test_admin_no_super_admin_no_puede_gestionar_categorias(): void
    {
        $this->withSession($this->adminSession)
            ->get('/admin/presupuesto-categorias')
            ->assertForbidden();
    }

    public function test_super_admin_gestiona_socios(): void
    {
        $this->withSession($this->superSession)
            ->post('/admin/socios', ['nombre' => 'Socio A', 'porcentaje' => 60, 'activo' => '1'])
            ->assertRedirect(route('admin.socios.index'));

        $socio = Socio::where('nombre', 'Socio A')->firstOrFail();

        $this->withSession($this->superSession)
            ->put("/admin/socios/{$socio->id}", ['nombre' => 'Socio A actualizado', 'porcentaje' => 60, 'activo' => '1'])
            ->assertRedirect(route('admin.socios.index'));

        $this->assertDatabaseHas('socios', ['id' => $socio->id, 'nombre' => 'Socio A actualizado']);

        $this->withSession($this->superSession)
            ->delete("/admin/socios/{$socio->id}")
            ->assertRedirect(route('admin.socios.index'));

        $this->assertDatabaseMissing('socios', ['id' => $socio->id]);
    }

    public function test_admin_no_super_admin_no_puede_gestionar_socios(): void
    {
        $this->withSession($this->adminSession)
            ->get('/admin/socios')
            ->assertForbidden();
    }

    public function test_liquidacion_show_muestra_preview_si_no_esta_liquidado(): void
    {
        $this->withSession($this->superSession)
            ->get("/admin/eventos/{$this->evento->id}/liquidacion")
            ->assertOk()
            ->assertViewHas('preview')
            ->assertViewHas('liquidacion', null);
    }

    public function test_liquidacion_store_liquida_evento_cerrado_con_socios_al_100(): void
    {
        // `event_testing` tiene socios persistentes de sesiones anteriores
        // (RefreshDatabase solo hace rollback de lo creado DENTRO de cada
        // test, no de datos ya committeados de antes) — se limpian acá
        // para que el 100% de este test sea real, no una suma con lo que
        // ya hubiera.
        Socio::query()->delete();
        Socio::create(['nombre' => 'Único socio', 'porcentaje' => 100, 'activo' => true]);
        $this->evento->update(['estado_evento_id' => 'closed']);

        $this->withSession($this->superSession)
            ->post("/admin/eventos/{$this->evento->id}/liquidacion")
            ->assertRedirect(route('admin.liquidacion.show', $this->evento->id));

        $this->assertDatabaseHas('liquidaciones', ['evento_id' => $this->evento->id]);
    }

    public function test_admin_no_super_admin_no_puede_ver_liquidacion(): void
    {
        $this->withSession($this->adminSession)
            ->get("/admin/eventos/{$this->evento->id}/liquidacion")
            ->assertForbidden();
    }
}

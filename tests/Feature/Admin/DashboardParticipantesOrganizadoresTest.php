<?php

namespace Tests\Feature\Admin;

use App\Actions\CrearInscripcionAction;
use App\DTOs\RegistrationDTO;
use App\Models\AdminUser;
use App\Models\Category;
use App\Models\Evento;
use App\Models\FormType;
use App\Models\Organizador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Consolidación monolito (21/08/2026), Fase 1e-i — Organizadores (CRUD +
 * formas de pago), Dashboard (listado de eventos), Dashboard de
 * inscripciones, Participantes (edición restringida) y Participantes
 * Detalle (reporte fila por fila). Mismo criterio de verificación que
 * fases anteriores: solo el wiring del panel, no la lógica de negocio (ya
 * cubierta en ApiRestEvent/tests/Feature/*).
 */
class DashboardParticipantesOrganizadoresTest extends TestCase
{
    use RefreshDatabase;

    private Evento $evento;
    private FormType $formType;
    private Category $categoria;
    private array $superSession;
    private array $adminSession;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->evento = Evento::factory()->create(['fee_pct' => 0.05]);
        $this->formType = FormType::factory()->create(['event_id' => $this->evento->id, 'activo' => true, 'requiere_categoria' => true]);
        $this->categoria = Category::factory()->create(['event_id' => $this->evento->id, 'price' => 50]);

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

    private function crearInscripcionPagada(string $numeroDocumento): \App\Models\Registration
    {
        $registration = app(CrearInscripcionAction::class)->handle(RegistrationDTO::fromArray([
            'referencia' => 'LA-TEST-' . uniqid(),
            'fecha' => now()->toDateTimeString(),
            'evento_id' => $this->evento->id,
            'evento_nombre' => $this->evento->nombre,
            'form_types_id' => $this->formType->id,
            'tipo_pago' => 'QR',
            'pago_status' => 'pending',
            'pay_order_number' => null,
            'totales' => ['inscripcion' => 50, 'donacion' => 0, 'souvenirs' => 0, 'fee' => 2.5, 'descuento' => 0, 'descuento_registrante' => 0, 'grand_total' => 52.5],
            'participantes' => [[
                'nombre' => 'Ana', 'apellido' => 'Prueba', 'alias' => '', 'genero' => 'Femenino',
                'tipoDocumento' => 'DNI', 'numeroDocumento' => $numeroDocumento,
                'polera' => '', 'precioPolera' => 0,
                'nacimiento' => ['dia' => 1, 'mes' => 1, 'anio' => 1995], 'edad' => 30,
                'correo' => 'ana' . rand(1, 999999) . '@test.net', 'direccion' => 'x', 'ciudad' => 'x', 'telefono' => '123',
                'souvenirs' => [], 'answers' => [],
                'categoria' => (string) $this->categoria->id, 'precioCategoria' => 50,
                'donacion' => 0, 'promoDescuento' => 0, 'promoCodigo' => '', 'subtotal' => 50,
            ]],
        ]));
        $registration->update(['pago_status' => 'paid']);

        return $registration;
    }

    public function test_dashboard_super_admin_ve_todos_los_eventos(): void
    {
        Evento::factory()->create();

        $this->withSession($this->superSession)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertViewHas('eventos', fn ($eventos) => count($eventos) === 2);
    }

    public function test_dashboard_admin_scoped_ve_solo_su_evento(): void
    {
        Evento::factory()->create();

        $this->withSession($this->adminSession)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertViewHas('eventos', fn ($eventos) => count($eventos) === 1 && $eventos[0]['id'] === $this->evento->id);
    }

    public function test_dashboard_inscripciones_renderiza(): void
    {
        $this->crearInscripcionPagada('10101010');

        $this->withSession($this->adminSession)
            ->get("/admin/eventos/{$this->evento->id}/dashboard")
            ->assertOk()
            ->assertViewHas('totalGeneral');
    }

    public function test_dashboard_inscripciones_csv_talleres_descarga(): void
    {
        $response = $this->withSession($this->adminSession)
            ->get("/admin/eventos/{$this->evento->id}/dashboard/talleres/csv");

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_admin_de_otro_evento_no_puede_ver_dashboard_inscripciones(): void
    {
        $otroEvento = Evento::factory()->create();

        $this->withSession($this->adminSession)
            ->get("/admin/eventos/{$otroEvento->id}/dashboard")
            ->assertForbidden();
    }

    public function test_participantes_index_y_update(): void
    {
        $registration = $this->crearInscripcionPagada('20202020');
        $participante = $registration->participants()->first();

        $this->withSession($this->adminSession)
            ->get("/admin/eventos/{$this->evento->id}/participantes")
            ->assertOk()
            ->assertSee('20202020');

        $this->withSession($this->adminSession)
            ->patch("/admin/eventos/{$this->evento->id}/participantes/{$participante->id}", [
                'nombre' => 'Nombre Editado',
            ])
            ->assertRedirect(route('admin.participantes.index', $this->evento->id));

        $this->assertDatabaseHas('participantes', ['id' => $participante->id, 'nombre' => 'Nombre Editado']);
    }

    public function test_participantes_detalle_index_y_csv(): void
    {
        $this->crearInscripcionPagada('30303030');

        $this->withSession($this->adminSession)
            ->get("/admin/eventos/{$this->evento->id}/participantes/detalle")
            ->assertOk()
            ->assertSee('30303030');

        $response = $this->withSession($this->adminSession)
            ->get("/admin/eventos/{$this->evento->id}/participantes/detalle/csv");

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_super_admin_gestiona_organizadores(): void
    {
        $this->withSession($this->superSession)
            ->post('/admin/organizadores', [
                'razon_social' => 'Eventos SRL',
                'email' => 'contacto@eventossrl.test',
                'activo' => '1',
            ])
            ->assertRedirect(route('admin.organizadores.index'));

        $this->assertDatabaseHas('organizadores', ['razon_social' => 'Eventos SRL', 'activo' => true]);

        $organizador = Organizador::where('razon_social', 'Eventos SRL')->first();

        $this->withSession($this->superSession)
            ->put("/admin/organizadores/{$organizador->id}", [
                'razon_social' => 'Eventos SRL Actualizado',
                'email' => 'contacto@eventossrl.test',
                'activo' => '1',
            ])
            ->assertRedirect(route('admin.organizadores.index'));

        $this->assertDatabaseHas('organizadores', ['id' => $organizador->id, 'razon_social' => 'Eventos SRL Actualizado']);

        $this->withSession($this->superSession)
            ->delete("/admin/organizadores/{$organizador->id}")
            ->assertRedirect(route('admin.organizadores.index'));

        $this->assertDatabaseMissing('organizadores', ['id' => $organizador->id]);
    }

    public function test_admin_no_super_admin_no_puede_gestionar_organizadores(): void
    {
        $this->withSession($this->adminSession)
            ->get('/admin/organizadores')
            ->assertForbidden();
    }

    public function test_formas_pago_de_organizador_lista_y_actualiza(): void
    {
        $organizador = Organizador::factory()->create();
        $fp = \App\Models\FormasPago::create(['slug' => 'transferencia', 'nombre' => 'Transferencia', 'tipo' => 'manual', 'activo' => true, 'organizador_id' => null]);

        $this->withSession($this->superSession)
            ->get("/admin/organizadores/{$organizador->id}/formas-pago")
            ->assertOk()
            ->assertSee('Transferencia');

        $this->withSession($this->superSession)
            ->put("/admin/organizadores/{$organizador->id}/formas-pago", ['forma_pago_ids' => [$fp->id]])
            ->assertRedirect(route('admin.organizadores.formas-pago', $organizador->id));

        $this->assertDatabaseHas('organizador_formas_pago', ['organizador_id' => $organizador->id, 'forma_pago_id' => $fp->id]);
    }
}

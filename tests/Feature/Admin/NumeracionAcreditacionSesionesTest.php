<?php

namespace Tests\Feature\Admin;

use App\Actions\CrearInscripcionAction;
use App\DTOs\RegistrationDTO;
use App\Models\AdminUser;
use App\Models\Category;
use App\Models\Evento;
use App\Models\FormType;
use App\Models\SesionCongreso;
use App\Models\Taller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Consolidación monolito (21/08/2026), Fase 1e-ii — Numeración,
 * Acreditación, ChronoTrack, Sesiones/Asistencia/Talleres de congreso.
 * Mismo criterio de verificación que fases anteriores: solo el wiring del
 * panel (rutas, sesión/rol, delegación in-process, vistas), no la lógica
 * de negocio (cubierta en ApiRestEvent/tests/Feature/*).
 */
class NumeracionAcreditacionSesionesTest extends TestCase
{
    use RefreshDatabase;

    private Evento $evento;
    private FormType $formType;
    private Category $categoria;
    private array $adminSession;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->evento = Evento::factory()->create(['fee_pct' => 0.05]);
        $this->formType = FormType::factory()->create(['event_id' => $this->evento->id, 'activo' => true, 'requiere_categoria' => true]);
        $this->categoria = Category::factory()->create(['event_id' => $this->evento->id, 'price' => 50]);

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

    // ── Numeración ──────────────────────────────────────────────────────

    public function test_numeracion_index_y_update(): void
    {
        $registration = $this->crearInscripcionPagada('11111111');
        $participante = $registration->participants()->first();

        $this->withSession($this->adminSession)
            ->get("/admin/eventos/{$this->evento->id}/numeracion")
            ->assertOk()
            ->assertSee('11111111');

        $this->withSession($this->adminSession)
            ->patch("/admin/numeracion/{$registration->referencia}/{$participante->id}", [
                'evento_id' => $this->evento->id,
                'numero_corredor' => '101',
                'chip' => 'CHIP-1',
            ])
            ->assertRedirect(route('admin.numeracion.index', ['event' => $this->evento->id, 'categoria' => null]));

        $this->assertDatabaseHas('participantes', ['id' => $participante->id, 'numero_corredor' => '101', 'chip' => 'CHIP-1']);
    }

    public function test_numeracion_csv_download(): void
    {
        $this->crearInscripcionPagada('22222222');

        $response = $this->withSession($this->adminSession)->get("/admin/eventos/{$this->evento->id}/numeracion/csv");

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    // ── Acreditación ─────────────────────────────────────────────────────

    public function test_acreditacion_index_lookup_y_checkin(): void
    {
        $registration = $this->crearInscripcionPagada('33333333');
        $participante = $registration->participants()->first();

        $this->withSession($this->adminSession)
            ->get("/admin/eventos/{$this->evento->id}/acreditacion")
            ->assertOk();

        $this->withSession($this->adminSession)
            ->postJson("/admin/eventos/{$this->evento->id}/acreditacion/lookup", ['referencia' => $registration->referencia])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->withSession($this->adminSession)
            ->patchJson("/admin/eventos/{$this->evento->id}/acreditacion/{$participante->id}")
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertNotNull($participante->fresh()->checked_in_at);
    }

    // ── ChronoTrack ──────────────────────────────────────────────────────

    public function test_chronotrack_index_renderiza(): void
    {
        $this->withSession($this->adminSession)
            ->get("/admin/eventos/{$this->evento->id}/resultados")
            ->assertOk();
    }

    public function test_chronotrack_sincronizar_sin_id_configurado_rechaza(): void
    {
        // El evento no tiene chronotrack_event_id — la API rechaza antes
        // de intentar ninguna llamada externa (ver
        // ResultadoController::sincronizarChronoTrack()).
        $this->withSession($this->adminSession)
            ->post("/admin/eventos/{$this->evento->id}/resultados/sincronizar")
            ->assertRedirect(route('admin.chronotrack.index', $this->evento->id))
            ->assertSessionHasErrors();
    }

    // ── Talleres ─────────────────────────────────────────────────────────

    public function test_talleres_crud(): void
    {
        $this->withSession($this->adminSession)
            ->post("/admin/eventos/{$this->evento->id}/talleres", [
                'nombre' => 'Taller de sutura',
                'modalidad' => 'OPTIONAL',
                'precio' => 100,
            ])
            ->assertRedirect(route('admin.talleres.index', $this->evento->id));

        $taller = Taller::where('evento_id', $this->evento->id)->firstOrFail();
        $this->assertSame('Taller de sutura', $taller->nombre);

        $this->withSession($this->adminSession)
            ->put("/admin/eventos/{$this->evento->id}/talleres/{$taller->id}", [
                'nombre' => 'Taller de sutura avanzada',
                'modalidad' => 'REQUIRED',
                'precio' => 150,
            ])
            ->assertRedirect(route('admin.talleres.index', $this->evento->id));

        $this->assertDatabaseHas('talleres', ['id' => $taller->id, 'nombre' => 'Taller de sutura avanzada', 'modalidad' => 'REQUIRED']);

        $this->withSession($this->adminSession)
            ->delete("/admin/eventos/{$this->evento->id}/talleres/{$taller->id}")
            ->assertRedirect(route('admin.talleres.index', $this->evento->id));

        $this->assertDatabaseMissing('talleres', ['id' => $taller->id]);
    }

    // ── Sesiones de congreso ─────────────────────────────────────────────

    public function test_sesiones_index_store_update_destroy(): void
    {
        $this->withSession($this->adminSession)
            ->get("/admin/eventos/{$this->evento->id}/sesiones")
            ->assertOk();

        $this->withSession($this->adminSession)
            ->post("/admin/eventos/{$this->evento->id}/sesiones", [
                'titulo' => 'Apertura',
                'fecha' => '2027-01-01',
                'hora_inicio' => '08:00',
                'hora_fin' => '09:00',
            ])
            ->assertRedirect(route('admin.sesiones.index', $this->evento->id));

        $sesion = SesionCongreso::where('evento_id', $this->evento->id)->firstOrFail();
        $this->assertSame('Apertura', $sesion->titulo);

        $this->withSession($this->adminSession)
            ->put("/admin/eventos/{$this->evento->id}/sesiones/{$sesion->id}", [
                'titulo' => 'Apertura oficial',
                'fecha' => '2027-01-01',
                'hora_inicio' => '08:00',
                'hora_fin' => '09:30',
            ])
            ->assertRedirect(route('admin.sesiones.index', $this->evento->id));

        $this->assertDatabaseHas('sesiones_congreso', ['id' => $sesion->id, 'titulo' => 'Apertura oficial']);

        $this->withSession($this->adminSession)
            ->delete("/admin/eventos/{$this->evento->id}/sesiones/{$sesion->id}")
            ->assertRedirect(route('admin.sesiones.index', $this->evento->id));

        $this->assertDatabaseMissing('sesiones_congreso', ['id' => $sesion->id]);
    }

    public function test_sesiones_reporte_renderiza(): void
    {
        $this->withSession($this->adminSession)
            ->get("/admin/eventos/{$this->evento->id}/sesiones-reporte")
            ->assertOk();
    }

    public function test_sesiones_asignar_y_desasignar_staff(): void
    {
        $formTypeStaff = FormType::factory()->create(['event_id' => $this->evento->id, 'activo' => true, 'requiere_categoria' => false, 'es_staff' => true, 'precio_base' => 0]);
        $registration = app(CrearInscripcionAction::class)->handle(RegistrationDTO::fromArray([
            'referencia' => 'LA-STAFF-' . uniqid(),
            'fecha' => now()->toDateTimeString(),
            'evento_id' => $this->evento->id,
            'evento_nombre' => $this->evento->nombre,
            'form_types_id' => $formTypeStaff->id,
            'tipo_pago' => 'QR',
            'pago_status' => 'paid',
            'pay_order_number' => null,
            'totales' => ['inscripcion' => 0, 'donacion' => 0, 'souvenirs' => 0, 'fee' => 0, 'descuento' => 0, 'descuento_registrante' => 0, 'grand_total' => 0],
            'participantes' => [[
                'nombre' => 'Staffer', 'apellido' => 'Uno', 'alias' => '', 'genero' => 'Masculino',
                'tipoDocumento' => 'DNI', 'numeroDocumento' => 'STAFF1',
                'polera' => '', 'precioPolera' => 0,
                'nacimiento' => ['dia' => 1, 'mes' => 1, 'anio' => 1990], 'edad' => 35,
                'correo' => 'staff@test.net', 'direccion' => 'x', 'ciudad' => 'x', 'telefono' => '123',
                'souvenirs' => [], 'answers' => [],
                'categoria' => null, 'precioCategoria' => 0,
                'donacion' => 0, 'promoDescuento' => 0, 'promoCodigo' => '', 'subtotal' => 0,
            ]],
        ]));
        $participante = $registration->participants()->first();

        $sesion = SesionCongreso::factory()->create(['evento_id' => $this->evento->id]);

        $this->withSession($this->adminSession)
            ->post("/admin/eventos/{$this->evento->id}/sesiones/{$sesion->id}/staff", [
                'participante_id' => $participante->id,
                'rol' => 'staff',
            ])
            ->assertRedirect(route('admin.sesiones.index', $this->evento->id));

        $this->assertDatabaseHas('sesion_congreso_staff', ['sesion_congreso_id' => $sesion->id, 'participante_id' => $participante->id]);

        $this->withSession($this->adminSession)
            ->delete("/admin/eventos/{$this->evento->id}/sesiones/{$sesion->id}/staff/{$participante->id}", ['rol' => 'staff'])
            ->assertRedirect(route('admin.sesiones.index', $this->evento->id));

        $this->assertDatabaseMissing('sesion_congreso_staff', ['sesion_congreso_id' => $sesion->id, 'participante_id' => $participante->id]);
    }

    // ── Asistencia por sesión ────────────────────────────────────────────

    public function test_asistencia_sesion_index_lookup_checkin_bulk(): void
    {
        $registration = $this->crearInscripcionPagada('44444444');
        $participante = $registration->participants()->first();
        $sesion = SesionCongreso::factory()->create(['evento_id' => $this->evento->id]);

        $this->withSession($this->adminSession)
            ->get("/admin/eventos/{$this->evento->id}/sesiones/{$sesion->id}/acreditacion")
            ->assertOk();

        $this->withSession($this->adminSession)
            ->postJson("/admin/eventos/{$this->evento->id}/sesiones/{$sesion->id}/acreditacion/lookup", ['referencia' => $registration->referencia])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->withSession($this->adminSession)
            ->postJson("/admin/eventos/{$this->evento->id}/sesiones/{$sesion->id}/acreditacion/checkin-bulk", [
                'participante_ids' => [$participante->id],
            ])
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_admin_de_otro_evento_no_puede_ver_numeracion(): void
    {
        $otroEvento = Evento::factory()->create();

        $this->withSession($this->adminSession)
            ->get("/admin/eventos/{$otroEvento->id}/numeracion")
            ->assertForbidden();
    }
}

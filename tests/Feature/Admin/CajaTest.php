<?php

namespace Tests\Feature\Admin;

use App\Actions\CrearInscripcionAction;
use App\DTOs\RegistrationDTO;
use App\Models\AdminUser;
use App\Models\Category;
use App\Models\Evento;
use App\Models\FormType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Consolidación monolito (21/08/2026), Fase 1d — Caja de cobro presencial.
 * Mismo patrón de verificación que Fases 1a/1b/1c: no re-testea la lógica
 * de negocio (turno obligatorio, cálculo de fee, contacto de emergencia
 * condicional, etc. — eso ya lo cubre ApiRestEvent/tests/Feature/CajaTest.php
 * a fondo), solo el WIRING del panel: rutas, sesión/rol, delegación
 * in-process a los controllers de la API, y que las vistas rendericen.
 */
class CajaTest extends TestCase
{
    use RefreshDatabase;

    private Evento $evento;
    private FormType $formType;
    private Category $categoria;
    private array $cajeroSession;
    private array $adminSession;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->evento = Evento::factory()->create(['fee_pct' => 0.05]);

        $this->formType = FormType::factory()->create([
            'event_id' => $this->evento->id,
            'activo' => true,
            'requiere_categoria' => true,
            'costo_edicion' => 10,
        ]);

        $this->categoria = Category::factory()->create([
            'event_id' => $this->evento->id,
            'price' => 50,
        ]);

        $cajero = AdminUser::create([
            'nombre' => 'Cajero', 'email' => 'cajero@example.net',
            'password' => Hash::make('secret123'), 'rol' => 'cajero',
            'activo' => true, 'evento_id' => $this->evento->id,
        ]);
        $this->cajeroSession = [
            'admin_token' => $cajero->createToken('t')->plainTextToken,
            'admin_user' => ['id' => $cajero->id, 'rol' => 'cajero', 'evento_id' => $this->evento->id],
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

    private function participanteData(string $numeroDocumento, array $overrides = []): array
    {
        return array_merge([
            'nombre' => 'Ana', 'apellido' => 'Prueba', 'alias' => '', 'genero' => 'Femenino',
            'tipoDocumento' => 'DNI', 'numeroDocumento' => $numeroDocumento,
            'polera' => '', 'precioPolera' => 0,
            'nacimiento' => ['dia' => 1, 'mes' => 1, 'anio' => 1995], 'edad' => 30,
            'correo' => 'ana' . rand(1, 999999) . '@test.net', 'direccion' => 'x', 'ciudad' => 'x', 'telefono' => '123',
            'contacto_emergencia' => ['nombre' => 'X', 'celular' => '123', 'relacion' => 'Madre'],
            'souvenirs' => [], 'answers' => [],
            'categoria' => (string) $this->categoria->id, 'precioCategoria' => 50,
            'donacion' => 0, 'promoDescuento' => 0, 'promoCodigo' => '', 'subtotal' => 50,
        ], $overrides);
    }

    private function totalesData(array $overrides = []): array
    {
        return array_merge([
            'inscripcion' => 50, 'donacion' => 0, 'souvenirs' => 0, 'fee' => 2.5,
            'descuento' => 0, 'descuento_registrante' => 0, 'grand_total' => 52.5,
        ], $overrides);
    }

    private function crearInscripcionPendiente(string $numeroDocumento): \App\Models\Registration
    {
        return app(CrearInscripcionAction::class)->handle(RegistrationDTO::fromArray([
            'referencia' => 'LA-TEST-' . uniqid(),
            'fecha' => now()->toDateTimeString(),
            'evento_id' => $this->evento->id,
            'evento_nombre' => $this->evento->nombre,
            'form_types_id' => $this->formType->id,
            'tipo_pago' => 'pendiente',
            'pago_status' => 'pending',
            'pay_order_number' => null,
            'totales' => $this->totalesData(),
            'participantes' => [$this->participanteData($numeroDocumento)],
        ]));
    }

    public function test_cajero_puede_ver_el_indice_de_caja_de_su_evento(): void
    {
        $this->withSession($this->cajeroSession)
            ->get("/admin/eventos/{$this->evento->id}/caja")
            ->assertOk()
            ->assertSee('Caja', false);
    }

    public function test_abrir_y_cerrar_turno(): void
    {
        $this->withSession($this->cajeroSession)
            ->post("/admin/eventos/{$this->evento->id}/caja/turno/abrir", ['fondo_inicial' => 100])
            ->assertRedirect(route('admin.caja.index', $this->evento->id));

        $this->assertDatabaseHas('caja_turnos', [
            'evento_id' => $this->evento->id,
            'fondo_inicial' => 100,
            'estado' => 'abierto',
        ]);

        $turnoId = \App\Models\CajaTurno::where('evento_id', $this->evento->id)->value('id');

        $response = $this->withSession($this->cajeroSession)
            ->post("/admin/eventos/{$this->evento->id}/caja/turno/{$turnoId}/cerrar", ['monto_contado' => 100]);

        $response->assertRedirect(route('admin.caja.index', $this->evento->id));
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('caja_turnos', ['id' => $turnoId, 'estado' => 'cerrado']);
    }

    public function test_alta_nueva_cobra_y_redirige_al_comprobante(): void
    {
        $this->withSession($this->cajeroSession)
            ->post("/admin/eventos/{$this->evento->id}/caja/turno/abrir", ['fondo_inicial' => 0]);

        $response = $this->withSession($this->cajeroSession)
            ->post("/admin/eventos/{$this->evento->id}/caja/nueva", [
                'form_types_id' => $this->formType->id,
                'participante_json' => json_encode($this->participanteData('20202020')),
                'totales_json' => json_encode($this->totalesData()),
            ]);

        $registro = \App\Models\Registration::where('evento_id', $this->evento->id)->first();
        $this->assertNotNull($registro, 'la inscripción debería haberse creado');
        $this->assertSame('paid', $registro->pago_status);

        $response->assertRedirect(route('admin.caja.eticket', [$this->evento->id, $registro->referencia]));

        $this->withSession($this->cajeroSession)
            ->get(route('admin.caja.eticket', [$this->evento->id, $registro->referencia]))
            ->assertOk();
    }

    public function test_alta_nueva_sin_turno_abierto_rechaza_con_errores_en_sesion(): void
    {
        $response = $this->withSession($this->cajeroSession)
            ->post("/admin/eventos/{$this->evento->id}/caja/nueva", [
                'form_types_id' => $this->formType->id,
                'participante_json' => json_encode($this->participanteData('21212121')),
                'totales_json' => json_encode($this->totalesData()),
            ]);

        $response->assertSessionHasErrors();
        $this->assertDatabaseMissing('participantes', ['numero_documento' => '21212121']);
    }

    public function test_buscar_y_buscar_persona_devuelven_json(): void
    {
        $registro = $this->crearInscripcionPendiente('30303030');

        $this->withSession($this->cajeroSession)
            ->getJson("/admin/eventos/{$this->evento->id}/caja/buscar/resultados?q={$registro->referencia}")
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->withSession($this->cajeroSession)
            ->getJson("/admin/eventos/{$this->evento->id}/caja/persona?numero_documento=99999999")
            ->assertOk()
            ->assertJson(['success' => true, 'data' => null]);
    }

    public function test_cobrar_pendiente_via_ajax(): void
    {
        $this->withSession($this->cajeroSession)
            ->post("/admin/eventos/{$this->evento->id}/caja/turno/abrir", ['fondo_inicial' => 0]);

        $registro = $this->crearInscripcionPendiente('40404040');

        $this->withSession($this->cajeroSession)
            ->postJson("/admin/eventos/{$this->evento->id}/caja/registrations/{$registro->referencia}/cobrar-pendiente")
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('registrations', ['id' => $registro->id, 'pago_status' => 'paid']);
    }

    public function test_editar_pendiente_actualiza_y_redirige(): void
    {
        $registro = $this->crearInscripcionPendiente('50505050');

        $response = $this->withSession($this->cajeroSession)
            ->post("/admin/eventos/{$this->evento->id}/caja/registrations/{$registro->referencia}/editar", [
                'pago_status' => 'pending',
                'participante_json' => json_encode($this->participanteData('50505050', ['nombre' => 'Editado'])),
                'totales_json' => json_encode($this->totalesData()),
            ]);

        $response->assertRedirect(route('admin.caja.index', $this->evento->id));
        $this->assertDatabaseHas('participantes', [
            'registration_id' => $registro->id,
            'nombre' => 'Editado',
        ]);
    }

    public function test_pantalla_editar_renderiza_con_los_datos_de_la_inscripcion(): void
    {
        $registro = $this->crearInscripcionPendiente('60606060');

        $this->withSession($this->cajeroSession)
            ->get(route('admin.caja.editar', [$this->evento->id, $registro->referencia]))
            ->assertOk();
    }

    // Separado en 2 métodos a propósito — simular 2 identidades
    // autenticadas distintas dentro del mismo test method choca con el
    // cacheo del guard de Sanctum entre llamadas (mismo criterio que
    // ApiRestEvent/tests/Feature/CajaTest.php, no es un bug del feature).
    public function test_cajero_no_puede_ver_cierres(): void
    {
        $this->withSession($this->cajeroSession)
            ->get("/admin/eventos/{$this->evento->id}/caja/cierres")
            ->assertForbidden();
    }

    public function test_admin_puede_ver_cierres(): void
    {
        $this->withSession($this->adminSession)
            ->get("/admin/eventos/{$this->evento->id}/caja/cierres")
            ->assertOk();
    }

    public function test_cajero_de_otro_evento_no_puede_operar_esta_caja(): void
    {
        $otroEvento = Evento::factory()->create();
        $cajeroAjeno = AdminUser::create([
            'nombre' => 'Cajero ajeno', 'email' => 'cajero-ajeno@example.net',
            'password' => Hash::make('secret123'), 'rol' => 'cajero',
            'activo' => true, 'evento_id' => $otroEvento->id,
        ]);
        $sesionAjena = [
            'admin_token' => $cajeroAjeno->createToken('t')->plainTextToken,
            'admin_user' => ['id' => $cajeroAjeno->id, 'rol' => 'cajero', 'evento_id' => $otroEvento->id],
        ];

        $this->withSession($sesionAjena)
            ->post("/admin/eventos/{$this->evento->id}/caja/turno/abrir", ['fondo_inicial' => 100])
            ->assertForbidden();
    }
}

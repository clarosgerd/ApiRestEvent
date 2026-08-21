<?php

namespace Tests\Feature;

use App\Actions\CrearInscripcionAction;
use App\DTOs\RegistrationDTO;
use App\Models\AdminUser;
use App\Models\CajaTurno;
use App\Models\Category;
use App\Models\Ciudad;
use App\Models\Evento;
use App\Models\FormType;
use App\Models\Organizador;
use App\Models\Pais;
use App\Models\Persona;
use App\Models\ContactoEmergencia;
use App\Models\SubtipoEvento;
use App\Models\TipoEvento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Caja de cobro presencial — ver PLAN-CAJA-COBRO-PRESENCIAL-14082026.md.
 * Cubre: scoping del rol cajero (incluida la regresión de
 * AuthorizesEventoScope::assertCanWriteEvento, que antes dejaba pasar
 * cualquier rol que no fuera literalmente 'admin'), la regla dura de
 * turno abierto, y que los 3 endpoints de cobro delegan correctamente a
 * las Actions existentes.
 */
class CajaTest extends TestCase
{
    use RefreshDatabase;

    private Evento $evento;

    private FormType $formType;

    private Category $categoria;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

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
            'fee_pct' => 0.05,
        ]);

        $this->formType = FormType::factory()->create([
            'event_id' => $this->evento->id,
            'cupo_total' => 100,
            'activo' => true,
            'requiere_categoria' => true,
            'costo_edicion' => 10,
        ]);

        $this->categoria = Category::factory()->create([
            'event_id' => $this->evento->id,
            'price' => 50,
        ]);
    }

    private function actingAsCajero(): \App\Models\AdminUser
    {
        $cajero = $this->actingAsAdmin();
        $cajero->update(['rol' => 'cajero', 'evento_id' => $this->evento->id]);

        return $cajero;
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

    public function test_cajero_no_puede_cobrar_sin_turno_abierto(): void
    {
        $this->actingAsCajero();

        $this->postJson("/api/v1/event/{$this->evento->id}/caja/inscripcion", [
            'form_types_id' => $this->formType->id,
            'participante' => $this->participanteData('11111111'),
            'totales' => $this->totalesData(),
        ])->assertStatus(422)->assertJsonFragment(['error' => 'Abrí un turno de caja antes de cobrar.']);
    }

    public function test_cajero_no_puede_abrir_dos_turnos(): void
    {
        $this->actingAsCajero();

        $this->postJson("/api/v1/event/{$this->evento->id}/caja/turno/abrir", ['fondo_inicial' => 100])
            ->assertStatus(201);

        $this->postJson("/api/v1/event/{$this->evento->id}/caja/turno/abrir", ['fondo_inicial' => 50])
            ->assertStatus(422);
    }

    public function test_cajero_de_otro_evento_no_puede_operar_esta_caja(): void
    {
        $otroEvento = Evento::factory()->create([
            'organizador_id' => $this->evento->organizador_id,
            'tipo_evento_id' => $this->evento->tipo_evento_id,
            'subtipo_evento_id' => $this->evento->subtipo_evento_id,
            'pais_id' => $this->evento->pais_id,
            'ciudad_id' => $this->evento->ciudad_id,
        ]);
        $cajero = $this->actingAsAdmin();
        $cajero->update(['rol' => 'cajero', 'evento_id' => $otroEvento->id]);

        $this->postJson("/api/v1/event/{$this->evento->id}/caja/turno/abrir", ['fondo_inicial' => 100])
            ->assertStatus(403);
    }

    /**
     * Regresión: antes de este feature, assertCanWriteEvento() solo
     * bloqueaba explícitamente rol==='admin' con evento distinto —
     * cualquier otro rol (como el nuevo 'cajero') pasaba de largo sin
     * chequeo. Un cajero no debe poder tocar pantallas fuera de la Caja
     * (acá, bodega de stock, protegida por assertCanWriteEvento()).
     */
    public function test_cajero_no_puede_acceder_a_pantallas_fuera_de_la_caja(): void
    {
        $this->actingAsCajero();

        $this->postJson("/api/v1/event/{$this->evento->id}/item-bodega", ['nombre' => 'Medalla'])
            ->assertStatus(403);
    }

    public function test_alta_y_cobro_de_inscripcion_nueva_por_caja(): void
    {
        $cajero = $this->actingAsCajero();
        $this->postJson("/api/v1/event/{$this->evento->id}/caja/turno/abrir", ['fondo_inicial' => 100])
            ->assertStatus(201);

        $response = $this->postJson("/api/v1/event/{$this->evento->id}/caja/inscripcion", [
            'form_types_id' => $this->formType->id,
            'participante' => $this->participanteData('22222222'),
            'totales' => $this->totalesData(),
        ]);

        $response->assertStatus(201)->assertJson(['success' => true]);

        $this->assertDatabaseHas('registrations', [
            'evento_id' => $this->evento->id,
            'pago_status' => 'paid',
            'tipo_pago' => 'EFECTIVO',
        ]);
        $this->assertDatabaseHas('caja_movimientos', [
            'evento_id' => $this->evento->id,
            'admin_user_id' => $cajero->id,
            'tipo' => 'inscripcion_nueva',
            'monto' => 52.5,
        ]);
    }

    public function test_cobrar_pendiente_existente(): void
    {
        $cajero = $this->actingAsCajero();
        $this->postJson("/api/v1/event/{$this->evento->id}/caja/turno/abrir", ['fondo_inicial' => 0]);

        $registration = app(CrearInscripcionAction::class)->handle(RegistrationDTO::fromArray([
            'referencia' => 'LA-TEST-' . uniqid(),
            'fecha' => now()->toDateTimeString(),
            'evento_id' => $this->evento->id,
            'evento_nombre' => $this->evento->nombre,
            'form_types_id' => $this->formType->id,
            'tipo_pago' => 'pendiente',
            'pago_status' => 'pending',
            'pay_order_number' => null,
            'totales' => $this->totalesData(),
            'participantes' => [$this->participanteData('33333333')],
        ]));

        $this->postJson("/api/v1/registrations/{$registration->referencia}/caja/cobrar-pendiente")
            ->assertStatus(200)->assertJson(['success' => true]);

        $this->assertDatabaseHas('registrations', ['id' => $registration->id, 'pago_status' => 'paid']);
        $this->assertDatabaseHas('caja_movimientos', [
            'registration_id' => $registration->id,
            'tipo' => 'cobro_pendiente',
            'monto' => 52.5,
        ]);
    }

    /**
     * Prellenado desde `personas` (20/08/2026) — devuelve null (no 404,
     * no error) cuando el documento no está en `personas` todavía; es un
     * prellenado opcional, no una búsqueda que deba fallar.
     */
    public function test_buscar_persona_devuelve_null_si_no_existe(): void
    {
        $this->actingAsCajero();

        $this->getJson("/api/v1/event/{$this->evento->id}/caja/persona?numero_documento=99999999")
            ->assertStatus(200)
            ->assertJson(['success' => true, 'data' => null]);
    }

    /**
     * Cubre el caso real: alguien que ya se inscribió a OTRO evento antes
     * (Persona es global, no por evento) — sus datos personales deben
     * venir listos para prellenar sin pedirle que retipee todo.
     */
    public function test_buscar_persona_devuelve_datos_si_existe(): void
    {
        Persona::factory()->create([
            'numero_documento' => '77778888',
            'nombre' => 'Carla',
            'apellido' => 'Vargas',
            'correo' => 'carla@test.net',
        ]);

        $this->actingAsCajero();

        $response = $this->getJson("/api/v1/event/{$this->evento->id}/caja/persona?numero_documento=77778888")
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $response->assertJsonPath('data.nombre', 'Carla');
        $response->assertJsonPath('data.apellido', 'Vargas');
        $response->assertJsonPath('data.numeroDocumento', '77778888');
        $response->assertJsonPath('data.correo', 'carla@test.net');
        $response->assertJsonStructure(['data' => ['nacimiento' => ['dia', 'mes', 'anio']]]);
    }

    public function test_buscar_persona_requiere_operar_caja(): void
    {
        Persona::factory()->create(['numero_documento' => '55556666']);
        $otroEvento = Evento::factory()->create([
            'organizador_id' => $this->evento->organizador_id,
            'tipo_evento_id' => $this->evento->tipo_evento_id,
            'subtipo_evento_id' => $this->evento->subtipo_evento_id,
            'pais_id' => $this->evento->pais_id,
            'ciudad_id' => $this->evento->ciudad_id,
        ]);
        $cajero = $this->actingAsAdmin();
        $cajero->update(['rol' => 'cajero', 'evento_id' => $otroEvento->id]);

        $this->getJson("/api/v1/event/{$this->evento->id}/caja/persona?numero_documento=55556666")
            ->assertStatus(403);
    }

    /**
     * Caja para eventos tipo congreso (20/08/2026) — sin el flag prendido
     * en el form_type (default true), el contacto de emergencia sigue
     * siendo obligatorio en Caja igual que antes de este feature.
     */
    public function test_caja_rechaza_inscripcion_nueva_sin_contacto_emergencia_por_default(): void
    {
        $this->actingAsCajero();
        $this->postJson("/api/v1/event/{$this->evento->id}/caja/turno/abrir", ['fondo_inicial' => 100]);

        $data = $this->participanteData('88888888');
        unset($data['contacto_emergencia']);

        $this->postJson("/api/v1/event/{$this->evento->id}/caja/inscripcion", [
            'form_types_id' => $this->formType->id,
            'participante'  => $data,
            'totales'       => $this->totalesData(),
        ])->assertUnprocessable();
    }

    /**
     * Caja para eventos tipo congreso (20/08/2026) — con
     * `requiere_contacto_emergencia=false` (evento tipo congreso, la
     * cajera lo desactivó porque el formulario le sobraba para este
     * tipo de inscripción), la alta nueva se acepta sin esos 3 campos.
     */
    public function test_caja_acepta_inscripcion_nueva_sin_contacto_emergencia_cuando_form_type_lo_desactiva(): void
    {
        $this->formType->update(['requiere_contacto_emergencia' => false]);
        $this->actingAsCajero();
        $this->postJson("/api/v1/event/{$this->evento->id}/caja/turno/abrir", ['fondo_inicial' => 100]);

        $data = $this->participanteData('99999999');
        unset($data['contacto_emergencia']);

        $this->postJson("/api/v1/event/{$this->evento->id}/caja/inscripcion", [
            'form_types_id' => $this->formType->id,
            'participante'  => $data,
            'totales'       => $this->totalesData(),
        ])->assertStatus(201)->assertJson(['success' => true]);

        $this->assertDatabaseHas('registrations', [
            'evento_id'    => $this->evento->id,
            'pago_status'  => 'paid',
        ]);
    }

    /**
     * Congresos con talleres desde Caja (20/08/2026) — mismo bug real ya
     * documentado en StoreRegistrationRequest ("$request->validated()
     * descartaba `talleres` en silencio" sin una regla explícita para la
     * clave). Cubre que `participante.talleres` y `totales.talleres`
     * sobrevivan la validación y lleguen a CrearInscripcionAction.
     */
    public function test_caja_persiste_taller_seleccionado_en_alta_nueva(): void
    {
        $taller = \App\Models\Taller::factory()->create([
            'evento_id' => $this->evento->id,
            'modalidad' => 'OPTIONAL',
            'precio'    => 30,
        ]);
        $sesion = \App\Models\SesionCongreso::factory()->create([
            'evento_id' => $this->evento->id,
            'taller_id' => $taller->id,
            'cupo'      => 10,
        ]);

        $this->actingAsCajero();
        $this->postJson("/api/v1/event/{$this->evento->id}/caja/turno/abrir", ['fondo_inicial' => 0]);

        $data = $this->participanteData('10101010');
        $data['talleres'] = [[
            'taller_id'          => $taller->id,
            'sesion_congreso_id' => $sesion->id,
        ]];

        $this->postJson("/api/v1/event/{$this->evento->id}/caja/inscripcion", [
            'form_types_id' => $this->formType->id,
            'participante'  => $data,
            // fee_incluye_talleres default true → fee = (50 + 30) * 5% = 4.
            'totales'       => $this->totalesData(['talleres' => 30, 'fee' => 4, 'grand_total' => 84]),
        ])->assertStatus(201)->assertJson(['success' => true]);

        $participante = \App\Models\Participante::where('numero_documento', '10101010')->first();
        $this->assertDatabaseHas('participante_taller_sesion', [
            'participante_id'    => $participante->id,
            'sesion_congreso_id' => $sesion->id,
        ]);
    }

    public function test_editar_pendiente_permite_cambiar_cualquier_campo_sin_turno(): void
    {
        $this->actingAsCajero();

        $registration = app(CrearInscripcionAction::class)->handle(RegistrationDTO::fromArray([
            'referencia' => 'LA-TEST-' . uniqid(),
            'fecha' => now()->toDateTimeString(),
            'evento_id' => $this->evento->id,
            'evento_nombre' => $this->evento->nombre,
            'form_types_id' => $this->formType->id,
            'tipo_pago' => 'pendiente',
            'pago_status' => 'pending',
            'pay_order_number' => null,
            'totales' => $this->totalesData(),
            'participantes' => [$this->participanteData('44444444')],
        ]));

        // Sin turno abierto — editar (sin cobrar) no lo exige.
        $this->patchJson("/api/v1/registrations/{$registration->referencia}/caja/editar-pendiente", [
            'participantes' => [$this->participanteData('44444444', ['nombre' => 'Nombre Corregido'])],
            'totales' => $this->totalesData(),
        ])->assertStatus(200)->assertJson(['success' => true]);

        $this->assertDatabaseHas('participantes', [
            'registration_id' => $registration->id,
            'nombre' => 'Nombre Corregido',
        ]);
    }

    public function test_editar_pagada_cobra_el_costo_edicion_configurado(): void
    {
        $cajero = $this->actingAsCajero();
        $this->postJson("/api/v1/event/{$this->evento->id}/caja/turno/abrir", ['fondo_inicial' => 0]);

        $registration = app(CrearInscripcionAction::class)->handle(RegistrationDTO::fromArray([
            'referencia' => 'LA-TEST-' . uniqid(),
            'fecha' => now()->toDateTimeString(),
            'evento_id' => $this->evento->id,
            'evento_nombre' => $this->evento->nombre,
            'form_types_id' => $this->formType->id,
            'tipo_pago' => 'pendiente',
            'pago_status' => 'pending',
            'pay_order_number' => null,
            'totales' => $this->totalesData(),
            'participantes' => [$this->participanteData('55555555')],
        ]));
        $registration->update(['pago_status' => 'paid']);

        $this->patchJson("/api/v1/registrations/{$registration->referencia}/caja/editar-pagada", [
            'confirmacion' => true,
            'participantes' => [$this->participanteData('55555555', ['nombre' => 'Editado Pagada'])],
            'totales' => $this->totalesData(),
        ])->assertStatus(200)->assertJson(['success' => true, 'costo_adicion' => 10]);

        $this->assertDatabaseHas('caja_movimientos', [
            'registration_id' => $registration->id,
            'tipo' => 'edicion_pagada',
            'monto' => 10,
            'admin_user_id' => $cajero->id,
        ]);
    }

    public function test_editar_pagada_sin_turno_abierto_rechaza(): void
    {
        $this->actingAsCajero();

        $registration = app(CrearInscripcionAction::class)->handle(RegistrationDTO::fromArray([
            'referencia' => 'LA-TEST-' . uniqid(),
            'fecha' => now()->toDateTimeString(),
            'evento_id' => $this->evento->id,
            'evento_nombre' => $this->evento->nombre,
            'form_types_id' => $this->formType->id,
            'tipo_pago' => 'pendiente',
            'pago_status' => 'pending',
            'pay_order_number' => null,
            'totales' => $this->totalesData(),
            'participantes' => [$this->participanteData('66666666')],
        ]));
        $registration->update(['pago_status' => 'paid']);

        $this->patchJson("/api/v1/registrations/{$registration->referencia}/caja/editar-pagada", [
            'confirmacion' => true,
            'participantes' => [$this->participanteData('66666666')],
            'totales' => $this->totalesData(),
        ])->assertStatus(422);
    }

    public function test_cerrar_turno_calcula_diferencia(): void
    {
        $cajero = $this->actingAsCajero();
        $turnoId = $this->postJson("/api/v1/event/{$this->evento->id}/caja/turno/abrir", ['fondo_inicial' => 100])
            ->json('turno.id');

        $this->postJson("/api/v1/event/{$this->evento->id}/caja/inscripcion", [
            'form_types_id' => $this->formType->id,
            'participante' => $this->participanteData('77777777'),
            'totales' => $this->totalesData(),
        ])->assertStatus(201);

        // Esperado = 100 (fondo) + 52.5 (cobrado) = 152.5. Cuenta 150 → faltante de 2.5.
        $response = $this->postJson("/api/v1/caja/turno/{$turnoId}/cerrar", ['monto_contado' => 150]);

        $response->assertStatus(200)->assertJson([
            'success' => true,
            'turno' => [
                'montoEsperado' => 152.5,
                'montoContado' => 150,
                'diferencia' => -2.5,
                'estado' => 'cerrado',
            ],
        ]);
    }

    public function test_cajero_no_puede_ver_el_listado_de_turnos_de_otros(): void
    {
        $this->actingAsCajero();

        $this->getJson("/api/v1/event/{$this->evento->id}/caja/turnos")
            ->assertStatus(403);
    }

    /**
     * Regresión: al agregar el rol 'cajero', StoreAdminUserRequest seguía
     * validando 'rol' con `in:super_admin,admin` — un POST /admin/users
     * con rol=cajero se rechazaba con 422 aunque el modelo/enum de BD ya
     * lo soportaran. Cubre también que evento_id sea obligatorio para
     * cajero (igual que para admin).
     */
    public function test_super_admin_puede_crear_usuario_cajero_via_admin_users(): void
    {
        $this->actingAsAdmin()->update(['rol' => 'super_admin']);

        $this->postJson('/api/v1/admin/users', [
            'nombre' => 'Cajero de prueba',
            'email' => 'cajero-prueba@test.net',
            'password' => 'password123',
            'rol' => 'cajero',
            'evento_id' => $this->evento->id,
        ])->assertStatus(201)->assertJson(['success' => true]);

        $this->assertDatabaseHas('admin_users', [
            'email' => 'cajero-prueba@test.net',
            'rol' => 'cajero',
            'evento_id' => $this->evento->id,
        ]);
    }

    public function test_crear_usuario_cajero_sin_evento_id_rechaza(): void
    {
        $this->actingAsAdmin()->update(['rol' => 'super_admin']);

        $this->postJson('/api/v1/admin/users', [
            'nombre' => 'Cajero sin evento',
            'email' => 'cajero-sin-evento@test.net',
            'password' => 'password123',
            'rol' => 'cajero',
        ])->assertStatus(422);
    }

    public function test_admin_del_evento_ve_el_listado_de_turnos(): void
    {
        // El turno se crea directo por Eloquent (no por HTTP) a propósito:
        // simular 2 identidades autenticadas distintas dentro del mismo
        // test method choca con el cacheo del guard de Sanctum entre
        // llamadas — no es un bug del feature, es una limitación de cómo
        // se simulan requests en este harness de test. Ver
        // test_cajero_no_puede_ver_el_listado_de_turnos_de_otros() para la
        // cobertura del lado del cajero.
        $cajero = AdminUser::factory()->create(['rol' => 'cajero', 'evento_id' => $this->evento->id]);
        CajaTurno::create([
            'evento_id' => $this->evento->id,
            'admin_user_id' => $cajero->id,
            'fondo_inicial' => 20,
            'estado' => 'abierto',
            'abierto_at' => now(),
        ]);

        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'admin', 'evento_id' => $this->evento->id]);

        $this->getJson("/api/v1/event/{$this->evento->id}/caja/turnos")
            ->assertStatus(200)
            ->assertJsonCount(1, 'turnos');
    }

    /**
     * Bug real reportado en UAT (no reproducible en local): "No tiene
     * acceso a la caja de este evento." al abrir turno con un cajero
     * correctamente scoped. Causa: `AdminUser::evento_id` no tenía cast,
     * y AuthorizesEventoScope comparaba con `!==` (estricto). Según cómo
     * el driver PDO de cada hosting devuelva columnas enteras (nativo vs
     * "stringify"), `$admin->evento_id` podía llegar como string
     * ("90013") mientras el parámetro de ruta llega como int (90013) —
     * en local el driver devuelve int nativo, en UAT devolvía string,
     * por eso solo fallaba ahí. Fix: cast `'evento_id' => 'integer'` en
     * el modelo (+ `(int)` explícito en las 2 comparaciones como defensa
     * adicional). Este test simula el valor "stringificado" que devolvía
     * el driver de UAT y confirma que el cast lo normaliza.
     */
    public function test_evento_id_se_castea_a_entero_sin_importar_como_lo_devuelva_el_driver(): void
    {
        $cajero = AdminUser::factory()->create(['rol' => 'cajero', 'evento_id' => $this->evento->id]);

        // Emula lo que un PDO con ATTR_STRINGIFY_FETCHES (u otra config
        // de emulación de prepares) devolvería para una columna entera.
        $cajero->setRawAttributes(array_merge(
            $cajero->getAttributes(),
            ['evento_id' => (string) $this->evento->id]
        ));

        $this->assertSame($this->evento->id, $cajero->evento_id);
        $this->assertIsInt($cajero->evento_id);
    }
}

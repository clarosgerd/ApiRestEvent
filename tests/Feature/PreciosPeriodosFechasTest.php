<?php

namespace Tests\Feature;

use App\Actions\CrearInscripcionAction;
use App\DTOs\RegistrationDTO;
use App\Models\Category;
use App\Models\CategoryPricePeriod;
use App\Models\Ciudad;
use App\Models\Evento;
use App\Models\FormType;
use App\Models\Organizador;
use App\Models\Pais;
use App\Models\SubtipoEvento;
use App\Models\TipoEvento;
use App\Support\PrecioVigenteData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Precios por período — ver PRD-precios-periodos-fechas.md. Cubre:
 * - PrecioVigenteData::paraCategoria() (4 casos de la regla de "precio
 *   vigente").
 * - Rechazo de overlap al crear/editar un período.
 * - CRUD scoped de períodos (admin de su evento, u otro evento, o
 *   super_admin).
 * - Cierre de la brecha de revalidación en CrearInscripcionAction, las
 *   2 ramas de `requiere_categoria` (sección 0 del PRD).
 */
class PreciosPeriodosFechasTest extends TestCase
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
        ]);

        $this->formType = FormType::factory()->create([
            'event_id' => $this->evento->id,
            'cupo_total' => 100,
            'activo' => true,
            'requiere_categoria' => true,
        ]);

        $this->categoria = Category::factory()->create([
            'event_id' => $this->evento->id,
            'price' => 80,
        ]);
    }

    // ==========================================
    // PrecioVigenteData::paraCategoria() — 4 casos
    // ==========================================

    public function test_sin_periodos_devuelve_el_price_de_la_categoria(): void
    {
        $vigente = PrecioVigenteData::paraCategoria($this->categoria);

        $this->assertSame(80.0, $vigente['precio']);
        $this->assertNull($vigente['periodo_nombre']);
        $this->assertNull($vigente['periodo_fecha_hasta']);
        $this->assertSame([], $vigente['periodos']);
    }

    public function test_periodo_vigente_hoy_gana_sobre_el_price_base(): void
    {
        CategoryPricePeriod::create([
            'category_id' => $this->categoria->id,
            'nombre' => 'Preventa',
            'price' => 50,
            'fecha_desde' => Carbon::today()->subDays(5),
            'fecha_hasta' => Carbon::today()->addDays(5),
        ]);

        $vigente = PrecioVigenteData::paraCategoria($this->categoria->fresh());

        $this->assertSame(50.0, $vigente['precio']);
        $this->assertSame('Preventa', $vigente['periodo_nombre']);
    }

    public function test_hueco_entre_periodos_cae_al_vencido_mas_reciente(): void
    {
        CategoryPricePeriod::create([
            'category_id' => $this->categoria->id,
            'nombre' => 'Preventa',
            'price' => 50,
            'fecha_desde' => Carbon::today()->subDays(20),
            'fecha_hasta' => Carbon::today()->subDays(10),
        ]);
        CategoryPricePeriod::create([
            'category_id' => $this->categoria->id,
            'nombre' => 'Precio regular',
            'price' => 65,
            'fecha_desde' => Carbon::today()->subDays(9),
            'fecha_hasta' => Carbon::today()->subDays(2),
        ]);
        // Hoy cae en el hueco entre "Precio regular" (venció hace 2 días)
        // y un eventual 3er período que todavía no se cargó.

        $vigente = PrecioVigenteData::paraCategoria($this->categoria->fresh());

        $this->assertSame(65.0, $vigente['precio']);
        $this->assertSame('Precio regular', $vigente['periodo_nombre']);
    }

    public function test_todos_los_periodos_futuros_cae_al_price_base(): void
    {
        CategoryPricePeriod::create([
            'category_id' => $this->categoria->id,
            'nombre' => 'Preventa',
            'price' => 50,
            'fecha_desde' => Carbon::today()->addDays(5),
            'fecha_hasta' => Carbon::today()->addDays(15),
        ]);

        $vigente = PrecioVigenteData::paraCategoria($this->categoria->fresh());

        $this->assertSame(80.0, $vigente['precio']); // categories.price, no el período futuro
        $this->assertNull($vigente['periodo_nombre']);
    }

    // ==========================================
    // CRUD de períodos — scoping y overlap
    // ==========================================

    public function test_admin_scoped_a_su_evento_puede_crear_periodo(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'admin', 'evento_id' => $this->evento->id]);

        $response = $this->postJson("/api/v1/category/{$this->categoria->id}/periodos", [
            'nombre' => 'Preventa',
            'price' => 50,
            'fecha_desde' => Carbon::today()->toDateString(),
            'fecha_hasta' => Carbon::today()->addDays(10)->toDateString(),
        ]);

        $response->assertStatus(201)->assertJson(['success' => true]);
        $this->assertDatabaseHas('category_price_periods', ['category_id' => $this->categoria->id, 'nombre' => 'Preventa']);
        // assertEquals, no assertSame — el número vuelve de un round-trip
        // JSON (int 50 en vez de float 50.0 si no trae decimales).
        $this->assertEquals(50, $response->json('category.precio_vigente'));
    }

    public function test_admin_de_otro_evento_no_puede_crear_periodo(): void
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

        $this->postJson("/api/v1/category/{$this->categoria->id}/periodos", [
            'nombre' => 'Preventa', 'price' => 50,
            'fecha_desde' => Carbon::today()->toDateString(),
            'fecha_hasta' => Carbon::today()->addDays(10)->toDateString(),
        ])->assertStatus(403);
    }

    public function test_rechaza_fecha_hasta_anterior_a_fecha_desde(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $this->postJson("/api/v1/category/{$this->categoria->id}/periodos", [
            'nombre' => 'Preventa', 'price' => 50,
            'fecha_desde' => Carbon::today()->toDateString(),
            'fecha_hasta' => Carbon::today()->subDay()->toDateString(),
        ])->assertStatus(422);
    }

    public function test_rechaza_overlap_con_otro_periodo_de_la_misma_categoria(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        CategoryPricePeriod::create([
            'category_id' => $this->categoria->id,
            'nombre' => 'Preventa',
            'price' => 50,
            'fecha_desde' => Carbon::today(),
            'fecha_hasta' => Carbon::today()->addDays(10),
        ]);

        $this->postJson("/api/v1/category/{$this->categoria->id}/periodos", [
            'nombre' => 'Precio regular', 'price' => 65,
            'fecha_desde' => Carbon::today()->addDays(5), // se pisa con el anterior
            'fecha_hasta' => Carbon::today()->addDays(20),
        ])->assertStatus(422)->assertJson(['success' => false]);
    }

    public function test_update_excluye_su_propio_periodo_del_chequeo_de_overlap(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $periodo = CategoryPricePeriod::create([
            'category_id' => $this->categoria->id,
            'nombre' => 'Preventa',
            'price' => 50,
            'fecha_desde' => Carbon::today(),
            'fecha_hasta' => Carbon::today()->addDays(10),
        ]);

        // Actualiza el precio del mismo período, mismas fechas — no debe
        // chocar contra sí mismo.
        $this->putJson("/api/v1/category-price-period/{$periodo->id}", [
            'nombre' => 'Preventa', 'price' => 55,
            'fecha_desde' => Carbon::today()->toDateString(),
            'fecha_hasta' => Carbon::today()->addDays(10)->toDateString(),
        ])->assertStatus(200)->assertJson(['success' => true]);

        $this->assertDatabaseHas('category_price_periods', ['id' => $periodo->id, 'price' => 55]);
    }

    public function test_destroy_elimina_el_periodo(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $periodo = CategoryPricePeriod::create([
            'category_id' => $this->categoria->id,
            'nombre' => 'Preventa',
            'price' => 50,
            'fecha_desde' => Carbon::today(),
            'fecha_hasta' => Carbon::today()->addDays(10),
        ]);

        $this->deleteJson("/api/v1/category-price-period/{$periodo->id}")
            ->assertStatus(200)->assertJson(['success' => true]);

        $this->assertDatabaseMissing('category_price_periods', ['id' => $periodo->id]);
    }

    // ==========================================
    // Cierre de la brecha de revalidación — 2 ramas de requiere_categoria
    // ==========================================

    private function dtoParaParticipante(array $overrides = []): RegistrationDTO
    {
        $participante = array_merge([
            'nombre' => 'Ana', 'apellido' => 'Prueba', 'alias' => '', 'genero' => 'Femenino',
            'tipoDocumento' => 'DNI', 'numeroDocumento' => (string) rand(10000000, 99999999),
            'polera' => '', 'precioPolera' => 0,
            'nacimiento' => ['dia' => 1, 'mes' => 1, 'anio' => 1995], 'edad' => 30,
            'correo' => 'ana'.rand(1, 999999).'@test.net', 'direccion' => 'x', 'ciudad' => 'x', 'telefono' => '123',
            'contacto_emergencia' => ['nombre' => 'X', 'celular' => '123', 'relacion' => 'Madre'],
            'souvenirs' => [], 'answers' => [],
            'categoria' => (string) $this->categoria->id, 'precioCategoria' => 80,
            'donacion' => 0, 'promoDescuento' => 0, 'promoCodigo' => '',
            'subtotal' => 80,
        ], $overrides);

        return RegistrationDTO::fromArray([
            'referencia' => 'LA-PRECIO-'.uniqid(),
            'fecha' => now()->toDateTimeString(),
            'evento_id' => $this->evento->id,
            'evento_nombre' => $this->evento->nombre,
            'form_types_id' => $this->formType->id,
            'tipo_pago' => 'pendiente',
            'pago_status' => 'pending',
            'pay_order_number' => null,
            'totales' => [
                'inscripcion' => 80, 'donacion' => 0, 'souvenirs' => 0, 'fee' => 4,
                'descuento' => 0, 'descuento_registrante' => 0, 'grand_total' => 84,
            ],
            'participantes' => [$participante],
        ]);
    }

    public function test_requiere_categoria_true_acepta_el_precio_vigente_de_la_categoria(): void
    {
        $registration = app(CrearInscripcionAction::class)->handle($this->dtoParaParticipante());

        $this->assertDatabaseHas('registrations', ['id' => $registration->id]);
    }

    public function test_requiere_categoria_true_rechaza_un_precio_que_no_coincide(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('precio de la categoría no coincide');

        app(CrearInscripcionAction::class)->handle($this->dtoParaParticipante(['precioCategoria' => 999]));
    }

    public function test_requiere_categoria_true_usa_el_precio_del_periodo_vigente_no_el_price_base(): void
    {
        CategoryPricePeriod::create([
            'category_id' => $this->categoria->id,
            'nombre' => 'Preventa',
            'price' => 50,
            'fecha_desde' => Carbon::today()->subDays(5),
            'fecha_hasta' => Carbon::today()->addDays(5),
        ]);

        // El price base (80) ya no es el vigente — mandarlo debe rechazarse.
        $this->expectException(\DomainException::class);
        app(CrearInscripcionAction::class)->handle($this->dtoParaParticipante(['precioCategoria' => 80]));
    }

    public function test_requiere_categoria_true_acepta_el_precio_del_periodo_vigente(): void
    {
        CategoryPricePeriod::create([
            'category_id' => $this->categoria->id,
            'nombre' => 'Preventa',
            'price' => 50,
            'fecha_desde' => Carbon::today()->subDays(5),
            'fecha_hasta' => Carbon::today()->addDays(5),
        ]);

        $registration = app(CrearInscripcionAction::class)->handle($this->dtoParaParticipante([
            'precioCategoria' => 50, 'subtotal' => 50,
        ]));

        $this->assertDatabaseHas('registrations', ['id' => $registration->id]);
    }

    public function test_requiere_categoria_false_cobra_precio_base_del_form_type(): void
    {
        $formTypeSinCategoria = FormType::factory()->create([
            'event_id' => $this->evento->id,
            'activo' => true,
            'requiere_categoria' => false,
            'precio_base' => 30,
        ]);

        $registration = app(CrearInscripcionAction::class)->handle($this->dtoConFormType($formTypeSinCategoria, 30));

        $this->assertDatabaseHas('registrations', ['id' => $registration->id]);
    }

    public function test_requiere_categoria_false_rechaza_un_precio_que_no_coincide_con_precio_base(): void
    {
        $formTypeSinCategoria = FormType::factory()->create([
            'event_id' => $this->evento->id,
            'activo' => true,
            'requiere_categoria' => false,
            'precio_base' => 30,
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('precio de inscripción no coincide');

        app(CrearInscripcionAction::class)->handle($this->dtoConFormType($formTypeSinCategoria, 0));
    }

    /**
     * DTO para un form_type sin categoría — `categoria` es el nombre del
     * form_type (mismo criterio que `elascenso/event/index.php`, ver
     * PRD sección 4) y `precioCategoria` es lo que el cliente dice que
     * vale la inscripción (lo que se está validando).
     */
    private function dtoConFormType(FormType $formType, float $precioCategoria): RegistrationDTO
    {
        $participante = [
            'nombre' => 'Ana', 'apellido' => 'Prueba', 'alias' => '', 'genero' => 'Femenino',
            'tipoDocumento' => 'DNI', 'numeroDocumento' => (string) rand(10000000, 99999999),
            'polera' => '', 'precioPolera' => 0,
            'nacimiento' => ['dia' => 1, 'mes' => 1, 'anio' => 1995], 'edad' => 30,
            'correo' => 'ana'.rand(1, 999999).'@test.net', 'direccion' => 'x', 'ciudad' => 'x', 'telefono' => '123',
            'contacto_emergencia' => ['nombre' => 'X', 'celular' => '123', 'relacion' => 'Madre'],
            'souvenirs' => [], 'answers' => [],
            'categoria' => $formType->name, 'precioCategoria' => $precioCategoria,
            'donacion' => 0, 'promoDescuento' => 0, 'promoCodigo' => '',
            'subtotal' => $precioCategoria,
        ];

        $fee = round($precioCategoria * 0.05, 2);

        return RegistrationDTO::fromArray([
            'referencia' => 'LA-PRECIO-'.uniqid(),
            'fecha' => now()->toDateTimeString(),
            'evento_id' => $this->evento->id,
            'evento_nombre' => $this->evento->nombre,
            'form_types_id' => $formType->id,
            'tipo_pago' => 'pendiente',
            'pago_status' => 'pending',
            'pay_order_number' => null,
            'totales' => [
                'inscripcion' => $precioCategoria, 'donacion' => 0, 'souvenirs' => 0, 'fee' => $fee,
                'descuento' => 0, 'descuento_registrante' => 0, 'grand_total' => $precioCategoria + $fee,
            ],
            'participantes' => [$participante],
        ]);
    }
}

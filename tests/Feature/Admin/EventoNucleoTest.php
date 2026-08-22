<?php

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use App\Models\Category;
use App\Models\Evento;
use App\Models\FormType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Consolidación monolito (21/08/2026), Fase 1c — núcleo (evento +
 * categorías/form_types/souvenirs/preguntas/promo codes/coordenadas/ruta/
 * auspiciadores/agenda). Mismo patrón de verificación que Fases 1a/1b. Ver
 * ApiRestEvent/brain/api_rest_event/PLAN-CONSOLIDACION-MONOLITO-21082026.md.
 */
class EventoNucleoTest extends TestCase
{
    use RefreshDatabase;

    private array $superSession;
    private array $adminSession;
    private Evento $evento;

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

    public function test_admin_scoped_no_super_admin_no_puede_crear_evento(): void
    {
        $this->withSession($this->adminSession)->get('/admin/eventos/create')->assertForbidden();
    }

    public function test_crear_evento_filtra_filas_en_blanco_del_formulario_antes_de_validar(): void
    {
        // CrearEventoAction() hardcodea varios defaults a id=1
        // (organizador_id, pais_id, ciudad_id, tipo_evento_id,
        // subtipo_evento_id) — "comportamiento histórico" que asume esos
        // ids ya existen (dato de seed real, no presente en una BD de test
        // recién refrescada). Se crean acá explícitos, no es parte de esta
        // consolidación, solo lo que hace falta para que el INSERT no
        // rompa por FK.
        \App\Models\Organizador::factory()->create(['id' => 1]);
        \App\Models\Pais::factory()->create(['id' => 1]);
        \App\Models\Ciudad::factory()->create(['id' => 1, 'pais_id' => 1]);
        \App\Models\TipoEvento::create(['id' => 1, 'nombre' => 'Carrera de Ruta', 'icono' => '🏃', 'activo' => true]);
        \App\Models\SubtipoEvento::create(['id' => 1, 'tipo_evento_id' => 1, 'nombre' => 'General', 'activo' => true]);

        $response = $this->withSession($this->superSession)->post('/admin/eventos', [
            'name' => 'Evento de prueba', 'description' => 'Descripción', 'date' => '2027-01-01',
            'location' => 'Santa Cruz',
            // Fila real + fila en blanco (el usuario tocó "+ Agregar
            // categoría" pero no completó nada) — StoreEventosRequest
            // exige `categories.*.name` con `required`, así que si esta
            // fila en blanco NO se filtra antes de validar, el POST entero
            // falla con 422 en vez de crear el evento con 1 sola categoría.
            'categories' => [
                ['name' => 'General', 'price' => '50'],
                ['name' => '', 'price' => ''],
            ],
            'organizador_id' => '',
        ]);

        // Actualizado 21/08/2026, Fase 1e-i: admin.dashboard (listado de
        // eventos) ya existe, así que redirectToDashboard() ya no cae al
        // fallback admin.catalogos.index — mismo código de siempre, solo
        // dejó de tomar la rama provisoria.
        $response->assertRedirect(route('admin.dashboard'));
        $evento = Evento::where('nombre', 'Evento de prueba')->firstOrFail();
        $this->assertCount(1, $evento->categories);
        // organizador_id="" -> el select "Sin organizador asignado" no
        // rompe la validación `integer` (se normaliza a null antes de
        // validar) — CrearEventoAction()::handle() default a 1 cuando no
        // viene ninguno, comportamiento preexistente, no de esta fase.
        $this->assertNotNull($evento->organizador_id);
    }

    public function test_editar_evento_renderiza_y_admin_scoped_puede_ver_el_suyo(): void
    {
        $this->withSession($this->adminSession)->get("/admin/eventos/{$this->evento->id}/edit")
            ->assertOk()->assertViewIs('admin.eventos.edit');
    }

    public function test_admin_scoped_no_puede_ver_evento_ajeno(): void
    {
        $otro = Evento::factory()->create();

        $this->withSession($this->adminSession)->get("/admin/eventos/{$otro->id}/edit")->assertForbidden();
    }

    public function test_actualizar_evento_convierte_porcentaje_humano_a_fraccion(): void
    {
        $response = $this->withSession($this->superSession)->put("/admin/eventos/{$this->evento->id}", [
            'name' => $this->evento->nombre,
            'feePctPorcentaje' => '7.5',
        ]);

        $response->assertRedirect(route('admin.eventos.edit', $this->evento).'#datos');
        $this->assertEqualsWithDelta(0.075, $this->evento->fresh()->fee_pct, 0.0001);
    }

    public function test_categoria_crud_completo_redirige_a_la_pestana_categorias(): void
    {
        $create = $this->withSession($this->adminSession)->post("/admin/eventos/{$this->evento->id}/categorias", [
            'name' => 'Categoria Test', 'price' => '100', 'price_usd' => '',
        ]);
        $create->assertRedirect(route('admin.eventos.edit', $this->evento).'#categorias');
        $categoria = Category::where('name', 'Categoria Test')->firstOrFail();
        $this->assertNull($categoria->price_usd);

        $update = $this->withSession($this->adminSession)->put("/admin/categorias/{$categoria->id}", [
            'name' => 'Categoria Editada', 'price' => '120', 'evento_id' => $this->evento->id,
        ]);
        $update->assertRedirect(route('admin.eventos.edit', $this->evento).'#categorias');
        $this->assertDatabaseHas('categories', ['id' => $categoria->id, 'name' => 'Categoria Editada']);

        $destroy = $this->withSession($this->adminSession)->delete("/admin/categorias/{$categoria->id}", ['evento_id' => $this->evento->id]);
        $destroy->assertRedirect(route('admin.eventos.edit', $this->evento).'#categorias');
        $this->assertDatabaseMissing('categories', ['id' => $categoria->id]);
    }

    public function test_periodos_de_precio_index_usa_categorycontroller_show_no_wrapped_en_data(): void
    {
        $categoria = Category::factory()->create(['event_id' => $this->evento->id]);

        $response = $this->withSession($this->adminSession)->get("/admin/categorias/{$categoria->id}/periodos?evento_id={$this->evento->id}");
        $response->assertOk()->assertViewIs('admin.categorias.periodos');
        $this->assertSame($categoria->id, $response->viewData('categoria')['id']);

        $store = $this->withSession($this->adminSession)->post("/admin/categorias/{$categoria->id}/periodos", [
            'evento_id' => $this->evento->id, 'nombre' => 'Preventa', 'price' => '80',
            'fecha_desde' => now()->subDay()->toDateString(), 'fecha_hasta' => now()->addDay()->toDateString(),
        ]);
        $store->assertRedirect();
        $this->assertDatabaseHas('category_price_periods', ['category_id' => $categoria->id, 'nombre' => 'Preventa']);
    }

    public function test_form_type_crud_completo_coerciona_checkboxes_a_boolean(): void
    {
        $create = $this->withSession($this->adminSession)->post("/admin/eventos/{$this->evento->id}/formtypes", [
            'name' => 'Individual', 'icon' => '🏃', 'description' => 'x', 'cupo_total' => '100',
            'precio_base' => '0', 'costo_edicion' => '0', 'tiempo_expiracion_min' => '30', 'color' => '#022858',
            // requiere_categoria NO viene tildado -> debe persistir como false, no quedar null/omitido.
        ]);
        $create->assertRedirect(route('admin.eventos.edit', $this->evento).'#tipos');
        $formType = FormType::where('name', 'Individual')->firstOrFail();
        $this->assertFalse((bool) $formType->requiere_categoria);

        $update = $this->withSession($this->adminSession)->put("/admin/formtypes/{$formType->id}", [
            'evento_id' => $this->evento->id, 'name' => 'Individual', 'icon' => '🏃', 'description' => 'x',
            'cupo_total' => '100', 'precio_base' => '0', 'costo_edicion' => '0', 'tiempo_expiracion_min' => '30',
            'color' => '#022858', 'requiere_categoria' => '1',
        ]);
        $update->assertRedirect(route('admin.eventos.edit', $this->evento).'#tipos');
        $this->assertTrue((bool) $formType->fresh()->requiere_categoria);

        $destroy = $this->withSession($this->adminSession)->delete("/admin/formtypes/{$formType->id}", ['evento_id' => $this->evento->id]);
        $destroy->assertRedirect(route('admin.eventos.edit', $this->evento).'#tipos');
        $this->assertDatabaseMissing('form_types', ['id' => $formType->id]);
    }

    public function test_souvenir_y_pregunta_delegan_correctamente(): void
    {
        $formType = FormType::factory()->create(['event_id' => $this->evento->id]);

        // 'icon' con algo cargado a propósito: la columna real es NOT NULL
        // sin default (mismatch preexistente con StoreSouvenirRequest, que
        // la valida `nullable` — no es parte de esta consolidación, ver
        // nota en PLAN-CONSOLIDACION-MONOLITO-21082026.md). Un '' se
        // convierte en null por ConvertEmptyStringsToNull (middleware
        // global de Laravel) y rompe el INSERT.
        $souvenir = $this->withSession($this->adminSession)->post("/admin/formtypes/{$formType->id}/souvenirs", [
            'evento_id' => $this->evento->id, 'name' => 'Polera', 'icon' => '👕', 'price' => '30',
        ]);
        $souvenir->assertRedirect(route('admin.eventos.edit', $this->evento).'#tipos');
        $this->assertDatabaseHas('souvenirs', ['form_types_id' => $formType->id, 'name' => 'Polera']);

        $pregunta = $this->withSession($this->adminSession)->post("/admin/formtypes/{$formType->id}/preguntas", [
            'evento_id' => $this->evento->id, 'seccion' => 'personal', 'nombre_campo' => 'alergias',
            'etiqueta' => '¿Alergias?', 'tipo_input' => 'text',
        ]);
        $pregunta->assertRedirect(route('admin.eventos.edit', $this->evento).'#tipos');
        $this->assertDatabaseHas('questions', ['form_types_id' => $formType->id, 'nombre_campo' => 'alergias']);
    }

    public function test_promo_code_coordenada_ruta_auspiciador_agenda_delegan_correctamente(): void
    {
        $promo = $this->withSession($this->adminSession)->post("/admin/eventos/{$this->evento->id}/promocodes", [
            'promo_code' => 'DESC10', 'discount_type' => 'percentage', 'discount_percent' => '0.1',
        ]);
        $promo->assertRedirect(route('admin.eventos.edit', $this->evento).'#promos');
        $this->assertDatabaseHas('promo_codes', ['promo_code' => 'DESC10', 'event_id' => $this->evento->id]);

        $coord = $this->withSession($this->adminSession)->post("/admin/eventos/{$this->evento->id}/coordenadas", [
            'lat' => '-17.78', 'lng' => '-63.18',
        ]);
        $coord->assertRedirect(route('admin.eventos.edit', $this->evento).'#mapa');
        $this->assertDatabaseHas('coordinates', ['event_id' => $this->evento->id]);

        $ruta = $this->withSession($this->adminSession)->post("/admin/eventos/{$this->evento->id}/ruta", [
            'lat' => '-17.79', 'lng' => '-63.19', 'label' => 'Salida',
        ]);
        $ruta->assertRedirect(route('admin.eventos.edit', $this->evento).'#mapa');
        $this->assertDatabaseHas('routes', ['event_id' => $this->evento->id, 'label' => 'Salida']);

        $auspiciador = $this->withSession($this->adminSession)->post("/admin/eventos/{$this->evento->id}/auspiciadores", [
            'nombre' => 'Coca-Cola', 'logo_url' => 'https://example.net/logo.png',
        ]);
        $auspiciador->assertRedirect(route('admin.eventos.edit', $this->evento).'#auspiciadores');
        $this->assertDatabaseHas('auspiciadores', ['nombre' => 'Coca-Cola', 'event_id' => $this->evento->id]);

        $agenda = $this->withSession($this->adminSession)->post("/admin/eventos/{$this->evento->id}/agenda", [
            'hora_inicio' => '09:00', 'titulo' => 'Bienvenida',
        ]);
        $agenda->assertRedirect(route('admin.eventos.edit', $this->evento).'#agenda');
        $this->assertDatabaseHas('agenda_items', ['titulo' => 'Bienvenida', 'event_id' => $this->evento->id]);
    }
}

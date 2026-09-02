<?php

namespace Tests\Feature;

use App\Actions\CrearInscripcionAction;
use App\DTOs\RegistrationDTO;
use App\Models\AdminUser;
use App\Models\Category;
use App\Models\Ciudad;
use App\Models\Evento;
use App\Models\FormType;
use App\Models\Organizador;
use App\Models\Pais;
use App\Models\Souvenir;
use App\Models\SubtipoEvento;
use App\Models\TipoEvento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Souvenirs invisibles para el participante (22/08/2026) — ver
 * migración add_visible_participante_to_souvenirs_table y
 * RegistrationService::injectSouvenirsInvisibles(). Escenario del
 * usuario: souvenirs de un form_type que se asignan solos, sin pasar
 * nunca por el formulario de inscripción, pensado para que
 * elascenso/delivery los vea en la lista de retiro en sitio sin que el
 * participante los haya elegido/visto.
 */
class SouvenirInvisibleTest extends TestCase
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
            'price' => 50,
        ]);
    }

    // ── Validación del CRUD ─────────────────────────────────

    public function test_super_admin_puede_crear_souvenir_invisible(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        // 'icon' explícito: souvenirs.icon es NOT NULL sin default en la
        // BD real, aunque StoreSouvenirRequest lo valide nullable — bug
        // preexistente ya documentado (no de esta feature), ver
        // project_plan_consolidacion_monolito.md.
        $this->postJson('/api/v1/souvenir', [
            'form_types_id' => $this->formType->id,
            'name' => 'Medalla especial',
            'price' => 0,
            'icon' => '🏅',
            'visible_participante' => false,
        ])->assertCreated()
            ->assertJsonPath('souvenir.visible_participante', false);

        $this->assertDatabaseHas('souvenirs', [
            'name' => 'Medalla especial',
            'visible_participante' => false,
        ]);
    }

    public function test_souvenir_nuevo_es_visible_por_defecto(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $this->postJson('/api/v1/souvenir', [
            'form_types_id' => $this->formType->id,
            'name' => 'Poncho',
            'price' => 10,
            'icon' => '🧢',
        ])->assertCreated()
            ->assertJsonPath('souvenir.visible_participante', true);
    }

    public function test_rechaza_souvenir_invisible_que_requiera_talla_o_sexo(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $this->postJson('/api/v1/souvenir', [
            'form_types_id' => $this->formType->id,
            'name' => 'Poncho',
            'price' => 10,
            'visible_participante' => false,
            'requiere_talla' => true,
        ])->assertStatus(422)->assertJsonValidationErrors(['visible_participante']);
    }

    public function test_rechaza_marcar_invisible_un_souvenir_que_ya_requiere_talla(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $souvenir = Souvenir::factory()->create([
            'form_types_id' => $this->formType->id,
            'requiere_talla' => true,
            'visible_participante' => true,
        ]);

        $this->putJson('/api/v1/souvenir/'.$souvenir->id, [
            'visible_participante' => false,
        ])->assertStatus(422)->assertJsonValidationErrors(['visible_participante']);
    }

    // ── Filtro público vs admin en EventoController ─────────

    public function test_publico_no_ve_souvenirs_invisibles(): void
    {
        Souvenir::factory()->create(['form_types_id' => $this->formType->id, 'name' => 'Visible', 'visible_participante' => true]);
        Souvenir::factory()->create(['form_types_id' => $this->formType->id, 'name' => 'Oculto', 'visible_participante' => false]);

        $nombres = $this->getJson('/api/v1/event/'.$this->evento->id)
            ->assertOk()
            ->json('eventos.formTypes.0.souvenirs.*.name');

        $this->assertContains('Visible', $nombres);
        $this->assertNotContains('Oculto', $nombres);
    }

    public function test_admin_scoped_a_ese_evento_si_ve_souvenirs_invisibles(): void
    {
        Souvenir::factory()->create(['form_types_id' => $this->formType->id, 'name' => 'Oculto', 'visible_participante' => false]);

        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'admin', 'evento_id' => $this->evento->id]);

        $nombres = $this->getJson('/api/v1/event/'.$this->evento->id)
            ->assertOk()
            ->json('eventos.formTypes.0.souvenirs.*.name');

        $this->assertContains('Oculto', $nombres);
    }

    public function test_admin_de_otro_evento_no_ve_souvenirs_invisibles_de_este(): void
    {
        Souvenir::factory()->create(['form_types_id' => $this->formType->id, 'name' => 'Oculto', 'visible_participante' => false]);

        $otroEvento = Evento::factory()->create();
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'admin', 'evento_id' => $otroEvento->id]);

        $nombres = $this->getJson('/api/v1/event/'.$this->evento->id)
            ->assertOk()
            ->json('eventos.formTypes.0.souvenirs.*.name');

        $this->assertNotContains('Oculto', $nombres);
    }

    public function test_super_admin_ve_souvenirs_invisibles_de_cualquier_evento(): void
    {
        Souvenir::factory()->create(['form_types_id' => $this->formType->id, 'name' => 'Oculto', 'visible_participante' => false]);

        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $nombres = $this->getJson('/api/v1/event/'.$this->evento->id)
            ->assertOk()
            ->json('eventos.formTypes.0.souvenirs.*.name');

        $this->assertContains('Oculto', $nombres);
    }

    public function test_listado_de_eventos_solo_muestra_ocultos_a_super_admin(): void
    {
        Souvenir::factory()->create(['form_types_id' => $this->formType->id, 'name' => 'Oculto', 'visible_participante' => false]);

        $publico = collect($this->getJson('/api/v1/event')->assertOk()->json('eventos'))
            ->firstWhere('id', $this->evento->id);
        $this->assertNotContains('Oculto', collect($publico['formTypes'][0]['souvenirs'])->pluck('name'));

        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);
        $comoSuperAdmin = collect($this->getJson('/api/v1/event')->assertOk()->json('eventos'))
            ->firstWhere('id', $this->evento->id);
        $this->assertContains('Oculto', collect($comoSuperAdmin['formTypes'][0]['souvenirs'])->pluck('name'));
    }

    // ── Inyección server-side en el registro ────────────────

    private function dtoParaParticipante(array $souvenirsEnviados = []): RegistrationDTO
    {
        $participante = [
            'nombre' => 'Ana', 'apellido' => 'Prueba', 'alias' => '',
            'genero' => 'Femenino', 'tipoDocumento' => 'DNI',
            'numeroDocumento' => (string) rand(10000000, 99999999),
            'polera' => '', 'precioPolera' => 0,
            'nacimiento' => ['dia' => 1, 'mes' => 1, 'anio' => 1995], 'edad' => 30,
            'correo' => 'ana'.rand(1, 999999).'@test.net',
            'direccion' => 'x', 'ciudad' => 'x', 'telefono' => '123',
            'contacto_emergencia' => ['nombre' => 'X', 'celular' => '123', 'relacion' => 'Madre'],
            'souvenirs' => $souvenirsEnviados,
            'answers' => [],
            'categoria' => (string) $this->categoria->id,
            'precioCategoria' => 50,
            'donacion' => 0, 'promoDescuento' => 0, 'promoCodigo' => '',
            'subtotal' => 50,
        ];

        return RegistrationDTO::fromArray([
            'referencia' => 'LA-TEST-'.uniqid(),
            'fecha' => now()->toDateTimeString(),
            'evento_id' => $this->evento->id,
            'evento_nombre' => $this->evento->nombre,
            'form_types_id' => $this->formType->id,
            'tipo_pago' => 'pendiente',
            'pago_status' => 'pending',
            'pay_order_number' => null,
            'totales' => [
                'inscripcion' => 50, 'donacion' => 0, 'souvenirs' => 0, 'fee' => 2.5,
                'descuento' => 0, 'descuento_registrante' => 0, 'grand_total' => 52.5,
            ],
            'participantes' => [$participante],
        ]);
    }

    public function test_registrarse_asigna_solo_el_souvenir_invisible_sin_que_el_cliente_lo_mande(): void
    {
        Souvenir::factory()->create(['form_types_id' => $this->formType->id, 'name' => 'Medalla especial', 'visible_participante' => false]);

        $registration = app(CrearInscripcionAction::class)->handle($this->dtoParaParticipante());

        $participante = $registration->participants()->first();
        $this->assertDatabaseHas('souvenir_participantes', [
            'participante_id' => $participante->id,
            'nombre' => 'Medalla especial',
            'precio' => 0,
        ]);
    }

    public function test_souvenir_invisible_no_se_duplica_ni_se_cobra_si_igual_llega_en_el_payload(): void
    {
        $invisible = Souvenir::factory()->create([
            'form_types_id' => $this->formType->id,
            'name' => 'Medalla especial',
            'price' => 99,
            'visible_participante' => false,
        ]);

        // Un cliente manipulado podría igual mandarlo con un precio real
        // — nunca debe cobrarse, y no debe duplicar la fila.
        $registration = app(CrearInscripcionAction::class)->handle($this->dtoParaParticipante([
            ['id' => $invisible->id, 'nombre' => 'Medalla especial', 'precio' => 99, 'talla' => null, 'sexo' => null],
        ]));

        $participante = $registration->participants()->first();
        $this->assertSame(1, $participante->souvenirParticipante()->where('souvenir_id', $invisible->id)->count());
    }

    public function test_souvenir_visible_no_se_asigna_solo(): void
    {
        Souvenir::factory()->create(['form_types_id' => $this->formType->id, 'name' => 'Poncho', 'visible_participante' => true]);

        $registration = app(CrearInscripcionAction::class)->handle($this->dtoParaParticipante());

        $participante = $registration->participants()->first();
        $this->assertDatabaseMissing('souvenir_participantes', [
            'participante_id' => $participante->id,
            'nombre' => 'Poncho',
        ]);
    }

    /**
     * Texto promocional por souvenir (02/09/2026) — texto libre opcional,
     * puramente de marketing (ej. "La mejor Coca-Cola bien fría"), sin
     * efecto en precio/disponibilidad. Se guarda en el alta, se puede
     * editar después, y se expone en el Resource para que
     * elascenso/event lo muestre en la tarjeta.
     */
    public function test_guarda_y_expone_el_texto_promocional(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $response = $this->postJson('/api/v1/souvenir', [
            'form_types_id' => $this->formType->id,
            'name' => 'Coca-Cola',
            'price' => 5,
            'icon' => '🥤',
            'texto_promocional' => 'La mejor Coca-Cola bien fría',
        ])->assertCreated();

        $souvenirId = $response->json('souvenir.id');
        $this->assertDatabaseHas('souvenirs', [
            'id' => $souvenirId,
            'texto_promocional' => 'La mejor Coca-Cola bien fría',
        ]);

        $this->putJson("/api/v1/souvenir/{$souvenirId}", [
            'texto_promocional' => 'Ahora con hielo',
        ])->assertOk()
            ->assertJsonPath('souvenir.texto_promocional', 'Ahora con hielo');
    }

    public function test_texto_promocional_es_opcional(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $this->postJson('/api/v1/souvenir', [
            'form_types_id' => $this->formType->id,
            'name' => 'Medalla',
            'price' => 0,
            'icon' => '🏅',
        ])->assertCreated()
            ->assertJsonPath('souvenir.texto_promocional', null);
    }
}

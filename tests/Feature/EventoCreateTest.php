<?php

namespace Tests\Feature;

use App\Models\AgendaItem;
use App\Models\Auspiciador;
use App\Models\Category;
use App\Models\Ciudad;
use App\Models\Coordinate;
use App\Models\Evento;
use App\Models\FormType;
use App\Models\Organizador;
use App\Models\Pais;
use App\Models\PromoCode;
use App\Models\Route;
use App\Models\SubtipoEvento;
use App\Models\Souvenir;
use App\Models\TipoEvento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * POST /event — App\Actions\CrearEventoAction. No existía ningún test
 * automatizado para este endpoint antes de esta suite (verificado antes de
 * escribirla) — la creación anidada solo se había probado a mano.
 */
class EventoCreateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // CrearEventoAction hardcodea pais_id/ciudad_id=1 y default a 1 para
        // organizador_id/tipo_evento_id/subtipo_evento_id si no vienen en el
        // payload (comportamiento heredado tal cual, no introducido por el
        // refactor) — y `eventos` tiene FK real sobre esas 5 columnas
        // (migración 2026_07_20_200005_add_foreign_keys_to_eventos_table).
        // `tipos_evento` en particular ya trae una fila id=1 sembrada por
        // una migración de datos (2026_08_05_120000_add_congreso_tipo_evento.php)
        // — de ahí el forceId() con firstOrNew en vez de create(['id'=>1])
        // (que no serviría: 'id' no está en $fillable, se ignora en mass
        // assignment; hay que setearlo por propiedad directa, que sí lo
        // respeta, y guardar).
        $pais = $this->forceId(Pais::class, 1, fn () => Pais::factory()->make());
        $this->forceId(Ciudad::class, 1, fn () => Ciudad::factory()->make(['pais_id' => $pais->id]));
        $this->forceId(Organizador::class, 1, fn () => Organizador::factory()->make());
        $tipoEvento = $this->forceId(TipoEvento::class, 1, fn () => TipoEvento::factory()->make());
        $this->forceId(SubtipoEvento::class, 1, fn () => SubtipoEvento::factory()->make(['tipo_evento_id' => $tipoEvento->id]));
    }

    /**
     * Garantiza que exista una fila con este id exacto — reusa la que ya
     * esté (algunas tablas vienen sembradas por una migración de datos) o
     * crea una nueva forzando el id por asignación directa de propiedad
     * (bypassea $fillable, a diferencia de create()/firstOrCreate()).
     */
    private function forceId(string $modelClass, int $id, \Closure $makeUnsaved)
    {
        $existing = $modelClass::find($id);
        if ($existing) {
            return $existing;
        }

        $model = $makeUnsaved();
        $model->id = $id;
        $model->save();

        return $model;
    }

    private function minimalPayload(array $overrides = []): array
    {
        return array_merge([
            'name'        => 'Evento de prueba',
            'description' => 'Descripción de prueba',
            'date'        => '2026-12-01 07:00:00',
            'location'    => 'La Paz',
            'categories'  => [
                ['name' => '5K', 'price' => 50],
            ],
        ], $overrides);
    }

    public function test_store_requires_super_admin(): void
    {
        $admin = $this->actingAsAdmin(); // rol super_admin por default en la factory
        \App\Models\AdminUser::where('id', $admin->id)->update(['rol' => 'admin']);

        $this->postJson('/api/v1/event', $this->minimalPayload())
            ->assertStatus(403);

        $this->assertDatabaseCount('eventos', 0);
    }

    public function test_store_rejects_unauthenticated(): void
    {
        $this->postJson('/api/v1/event', $this->minimalPayload())
            ->assertStatus(401);
    }

    public function test_store_creates_minimal_event(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/v1/event', $this->minimalPayload());

        $response->assertStatus(201)
            ->assertJson(['success' => true]);

        $this->assertDatabaseCount('eventos', 1);
        $evento = Evento::first();
        $this->assertSame('Evento de prueba', $evento->nombre);
        // publicado nace en false salvo que se pida explícito — ver
        // EventoDTO::fromArray().
        $this->assertFalse((bool) $evento->publicado);
        $this->assertSame(1, Category::where('event_id', $evento->id)->count());
    }

    public function test_store_creates_event_with_all_nested_data(): void
    {
        $this->actingAsAdmin();

        $payload = $this->minimalPayload([
            'chronotrackEventId' => '93491',
            'coordinates' => [['lat' => -16.5, 'lng' => -68.1]],
            'route'       => [['lat' => -16.5, 'lng' => -68.1, 'label' => 'Salida']],
            'promoCodes'  => [
                ['promo_code' => 'TEST10', 'price' => 0, 'discount_type' => 'percentage', 'discount_percent' => 0.10],
            ],
            'auspiciadores' => [
                ['nombre' => 'Sponsor X', 'logo_url' => 'x.png', 'contacto' => 'x', 'orden' => 1],
            ],
            'formTypes' => [[
                'name' => 'Individual',
                'cupo_total' => 10,
                'precio_base' => 50,
                'has_team' => false,
                'has_delivery' => false,
                'hasDonation' => true,
                'hasPromoCode' => false,
                'souvenirs' => [
                    ['name' => 'Buff', 'price' => 5],
                ],
            ]],
            'agenda' => [[
                'formTypeName' => 'Individual',
                'date' => '2026-12-01',
                'startTime' => '07:00',
                'endTime' => '08:00',
                'title' => 'Largada',
            ]],
        ]);

        $response = $this->postJson('/api/v1/event', $payload);

        $response->assertStatus(201)->assertJson(['success' => true]);

        $evento = Evento::first();
        $this->assertSame('93491', $evento->chronotrack_event_id);
        $this->assertSame(1, Coordinate::where('event_id', $evento->id)->count());
        $this->assertSame(1, Route::where('event_id', $evento->id)->count());
        $this->assertSame(1, Category::where('event_id', $evento->id)->count());
        $this->assertSame(1, PromoCode::where('event_id', $evento->id)->count());
        $this->assertSame(1, Auspiciador::where('event_id', $evento->id)->count());
        $this->assertSame(1, FormType::where('event_id', $evento->id)->count());

        $formType = FormType::where('event_id', $evento->id)->first();
        $this->assertSame(1, Souvenir::where('form_types_id', $formType->id)->count());
        // hasDonation/hasPromoCode pasaron de `eventos` a `form_types`
        // (QA visual, 10/08) — la creación anidada vía POST /event debe
        // persistirlos por form_type, no a nivel evento.
        $this->assertTrue((bool) $formType->has_donation);
        $this->assertFalse((bool) $formType->has_promo_code);

        // Punto más delicado de mover a la Action: createAgendaItems()
        // resuelve formTypeName -> id real buscando entre los form_types
        // que createFormTypes() ya creó unos pasos antes en la misma
        // llamada — si el orden se rompiera, esto quedaría en null.
        $agendaItem = AgendaItem::where('event_id', $evento->id)->first();
        $this->assertNotNull($agendaItem);
        $this->assertSame($formType->id, $agendaItem->form_type_id);
    }

    public function test_store_defaults_organizador_and_tipo_evento_when_omitted(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/event', $this->minimalPayload())
            ->assertStatus(201);

        $evento = Evento::first();
        // Default histórico documentado en CrearEventoAction — no romper
        // callers viejos que todavía no mandan estos campos.
        $this->assertSame(1, $evento->organizador_id);
        $this->assertSame(1, $evento->tipo_evento_id);
        $this->assertSame(1, $evento->subtipo_evento_id);
    }
}

<?php

namespace Tests\Feature;

use App\Actions\ClonarEventoAction;
use App\Models\AgendaItem;
use App\Models\Auspiciador;
use App\Models\Category;
use App\Models\CategoryPricePeriod;
use App\Models\Coordinate;
use App\Models\Evento;
use App\Models\FormType;
use App\Models\FormularioCampos;
use App\Models\Genero;
use App\Models\ItemStock;
use App\Models\NumeracionRango;
use App\Models\Participante;
use App\Models\PromoCode;
use App\Models\QuestionOptions;
use App\Models\Registration;
use App\Models\Route;
use App\Models\SesionCongreso;
use App\Models\Souvenir;
use App\Models\Taller;
use App\Models\TallerCombo;
use App\Models\TallerComboPrecio;
use App\Models\TallerPricePeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Clonar evento (23/10/2026, pedido real del usuario) — duplica toda la
 * configuración de un evento sin ningún dato transaccional. Ver
 * ClonarEventoAction.
 */
class ClonarEventoTest extends TestCase
{
    use RefreshDatabase;

    /** Arma un evento con toda la configuración posible + datos transaccionales de control. */
    private function eventoCompleto(): Evento
    {
        $evento = Evento::factory()->create(['nombre' => 'Original']);

        Coordinate::create(['event_id' => $evento->id, 'lat' => -17.78, 'lng' => -63.18]);
        Route::create(['event_id' => $evento->id, 'lat' => -17.78, 'lng' => -63.18, 'label' => 'Salida']);

        $categoria = Category::factory()->create(['event_id' => $evento->id]);
        CategoryPricePeriod::create([
            'category_id' => $categoria->id, 'nombre' => 'Preventa', 'price' => 100,
            'fecha_desde' => now()->subDays(5), 'fecha_hasta' => now()->addDays(5),
        ]);
        // Dato transaccional — NO debe clonarse.
        NumeracionRango::create([
            'category_id' => $categoria->id, 'genero_id' => Genero::first()->id,
            'edad_min' => 0, 'edad_max' => 99, 'color' => '#abcdef',
            'numero_min' => 1, 'numero_max' => 100,
        ]);

        $formType = FormType::factory()->create(['event_id' => $evento->id]);
        $souvenir = Souvenir::factory()->create(['form_types_id' => $formType->id]);
        // Dato transaccional — NO debe clonarse.
        ItemStock::create(['souvenir_id' => $souvenir->id, 'talla' => 'M', 'cantidad_total' => 50]);
        $pregunta = FormularioCampos::factory()->create([
            'form_types_id' => $formType->id, 'nombre_campo' => 'especialidad', 'seccion' => 'otro',
            'etiqueta' => '¿Especialidad?', 'tipo_input' => 'select', 'obligatorio' => true, 'orden' => 1,
        ]);
        QuestionOptions::create(['question_id' => $pregunta->id, 'option_text' => 'Cardiología', 'order' => 1]);

        $agendaItem = AgendaItem::create([
            'event_id' => $evento->id, 'form_type_id' => $formType->id, 'titulo' => 'Apertura',
            'fecha' => now()->toDateString(), 'hora_inicio' => '08:00', 'hora_fin' => '09:00', 'orden' => 1,
        ]);

        $taller = Taller::factory()->create(['evento_id' => $evento->id]);
        TallerPricePeriod::create([
            'taller_id' => $taller->id, 'category_id' => $categoria->id, 'nombre' => 'Preventa',
            'price' => 50, 'fecha_desde' => now()->subDays(5), 'fecha_hasta' => now()->addDays(5),
        ]);
        $sesion = SesionCongreso::factory()->create([
            'evento_id' => $evento->id, 'taller_id' => $taller->id, 'agenda_item_id' => $agendaItem->id,
        ]);

        $combo = TallerCombo::create(['evento_id' => $evento->id, 'nombre' => 'Combo A', 'activo' => true]);
        $combo->talleres()->attach($taller->id);
        TallerComboPrecio::create(['taller_combo_id' => $combo->id, 'category_id' => $categoria->id, 'precio' => 80]);

        PromoCode::factory()->create(['event_id' => $evento->id, 'promo_code' => 'ORIGINAL10']);
        Auspiciador::create(['event_id' => $evento->id, 'nombre' => 'Sponsor', 'logo_url' => 'https://example.com/logo.png', 'orden' => 1]);

        // Datos transaccionales — NO deben clonarse.
        $registration = Registration::create([
            'referencia' => 'REF' . rand(100000, 999999), 'fecha' => now(), 'evento_id' => $evento->id,
            'form_types_id' => $formType->id, 'evento_nombre' => $evento->nombre, 'tipo_pago' => 'sip',
            'pago_status' => 'paid',
        ]);
        Participante::create([
            'registration_id' => $registration->id, 'nombre' => 'Juan', 'apellido' => 'Perez',
            'genero' => 'Masculino', 'tipo_documento' => 'DNI', 'numero_documento' => '12345678',
            'fecha_nacimiento' => '1990-01-01', 'edad' => 35, 'correo' => 'juan@test.net',
            'direccion' => 'x', 'ciudad' => 'x', 'telefono' => '123',
            'categoria' => $categoria->id, 'precio_categoria' => 100, 'subtotal' => 100,
        ]);

        return $evento->fresh();
    }

    public function test_clona_toda_la_configuracion_con_las_mismas_cantidades(): void
    {
        $original = $this->eventoCompleto();

        $clon = app(ClonarEventoAction::class)->handle($original, 'Clon de prueba');

        $this->assertNotSame($original->id, $clon->id);
        $this->assertSame(1, Coordinate::where('event_id', $clon->id)->count());
        $this->assertSame(1, Route::where('event_id', $clon->id)->count());
        $this->assertSame(1, Category::where('event_id', $clon->id)->count());
        $this->assertSame(1, CategoryPricePeriod::whereIn('category_id', Category::where('event_id', $clon->id)->pluck('id'))->count());
        $this->assertSame(1, FormType::where('event_id', $clon->id)->count());
        $this->assertSame(1, Souvenir::whereIn('form_types_id', FormType::where('event_id', $clon->id)->pluck('id'))->count());
        $this->assertSame(1, FormularioCampos::whereIn('form_types_id', FormType::where('event_id', $clon->id)->pluck('id'))->count());
        $this->assertSame(1, AgendaItem::where('event_id', $clon->id)->count());
        $this->assertSame(1, Taller::where('evento_id', $clon->id)->count());
        $this->assertSame(1, TallerPricePeriod::whereIn('taller_id', Taller::where('evento_id', $clon->id)->pluck('id'))->count());
        $this->assertSame(1, SesionCongreso::where('evento_id', $clon->id)->count());
        $this->assertSame(1, TallerCombo::where('evento_id', $clon->id)->count());
        $this->assertSame(1, PromoCode::where('event_id', $clon->id)->count());
        $this->assertSame(1, Auspiciador::where('event_id', $clon->id)->count());
    }

    public function test_remapea_las_foreign_keys_a_las_filas_clonadas_no_a_las_originales(): void
    {
        $original = $this->eventoCompleto();
        $clon = app(ClonarEventoAction::class)->handle($original, 'Clon de prueba');

        $categoriaClon = Category::where('event_id', $clon->id)->firstOrFail();
        $formTypeClon = FormType::where('event_id', $clon->id)->firstOrFail();
        $tallerClon = Taller::where('evento_id', $clon->id)->firstOrFail();
        $agendaItemClon = AgendaItem::where('event_id', $clon->id)->firstOrFail();

        $tallerPricePeriodClon = TallerPricePeriod::where('taller_id', $tallerClon->id)->firstOrFail();
        $this->assertSame($categoriaClon->id, $tallerPricePeriodClon->category_id);

        $sesionClon = SesionCongreso::where('evento_id', $clon->id)->firstOrFail();
        $this->assertSame($tallerClon->id, $sesionClon->taller_id);
        $this->assertSame($agendaItemClon->id, $sesionClon->agenda_item_id);

        $comboClon = TallerCombo::where('evento_id', $clon->id)->firstOrFail();
        $this->assertTrue($comboClon->talleres->pluck('id')->contains($tallerClon->id));
        $precioComboClon = TallerComboPrecio::where('taller_combo_id', $comboClon->id)->firstOrFail();
        $this->assertSame($categoriaClon->id, $precioComboClon->category_id);

        $this->assertSame($formTypeClon->id, $agendaItemClon->form_type_id);
    }

    public function test_no_clona_nada_transaccional(): void
    {
        $original = $this->eventoCompleto();
        $clon = app(ClonarEventoAction::class)->handle($original, 'Clon de prueba');

        $this->assertSame(0, Registration::where('evento_id', $clon->id)->count());

        $categoriaClon = Category::where('event_id', $clon->id)->firstOrFail();
        $this->assertSame(0, NumeracionRango::where('category_id', $categoriaClon->id)->count());

        $souvenirClon = Souvenir::whereIn('form_types_id', FormType::where('event_id', $clon->id)->pluck('id'))->firstOrFail();
        $this->assertSame(0, ItemStock::where('souvenir_id', $souvenirClon->id)->count());

        // El original no se ve afectado por el clonado.
        $this->assertSame(1, Registration::where('evento_id', $original->id)->count());
    }

    public function test_resetea_campos_del_evento_base(): void
    {
        $original = $this->eventoCompleto();
        $original->update(['publicado' => true]);

        $clon = app(ClonarEventoAction::class)->handle($original->fresh(), 'Clon de prueba');

        $this->assertSame('Clon de prueba', $clon->nombre);
        $this->assertNotSame($original->url_slug, $clon->url_slug);
        $this->assertFalse((bool) $clon->publicado);
        $this->assertSame(0, $clon->contador_visitas);
        // Las fechas se copian tal cual (decisión confirmada con el usuario).
        $this->assertEquals(
            \Carbon\Carbon::parse($original->fecha_inicio)->toDateString(),
            \Carbon\Carbon::parse($clon->fecha_inicio)->toDateString(),
        );
    }

    public function test_promo_code_clonado_tiene_codigo_distinto_y_contador_en_cero(): void
    {
        $original = $this->eventoCompleto();
        $clon = app(ClonarEventoAction::class)->handle($original, 'Clon de prueba');

        $promoOriginal = PromoCode::where('event_id', $original->id)->firstOrFail();
        $promoClon = PromoCode::where('event_id', $clon->id)->firstOrFail();

        $this->assertNotSame($promoOriginal->promo_code, $promoClon->promo_code);
        $this->assertStringStartsWith($promoOriginal->promo_code . '-', $promoClon->promo_code);
        $this->assertSame(0, $promoClon->times_used);
        $this->assertFalse((bool) $promoClon->usado);
        $this->assertNull($promoClon->registration_id);
    }
}

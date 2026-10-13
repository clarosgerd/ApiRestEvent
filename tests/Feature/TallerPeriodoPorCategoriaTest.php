<?php

namespace Tests\Feature;

use App\Actions\CrearInscripcionAction;
use App\DTOs\RegistrationDTO;
use App\Models\Category;
use App\Models\Ciudad;
use App\Models\Evento;
use App\Models\FormType;
use App\Models\Organizador;
use App\Models\Pais;
use App\Models\SesionCongreso;
use App\Models\SubtipoEvento;
use App\Models\Taller;
use App\Models\TallerPricePeriod;
use App\Models\TipoEvento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Período de precio de taller por categoría (13/10/2026, pedido del
 * usuario) — end-to-end, mismo espíritu que TallerComboTest pero para
 * períodos: dos participantes de categorías distintas se inscriben al
 * mismo taller/sesión, uno con período propio ("Especialista", Bs 100)
 * vigente hoy, el otro sin período propio (usa el general, Bs 60) — cada
 * uno debe cobrar exactamente lo que le corresponde a SU categoría.
 */
class TallerPeriodoPorCategoriaTest extends TestCase
{
    use RefreshDatabase;

    public function test_cada_categoria_cobra_el_periodo_que_le_corresponde_al_inscribirse(): void
    {
        Mail::fake();

        $pais = Pais::factory()->create();
        $ciudad = Ciudad::factory()->create(['pais_id' => $pais->id]);
        $organizador = Organizador::factory()->create();
        $tipoEvento = TipoEvento::factory()->create();
        $subtipoEvento = SubtipoEvento::factory()->create(['tipo_evento_id' => $tipoEvento->id]);

        $evento = Evento::factory()->create([
            'organizador_id' => $organizador->id,
            'tipo_evento_id' => $tipoEvento->id,
            'subtipo_evento_id' => $subtipoEvento->id,
            'pais_id' => $pais->id,
            'ciudad_id' => $ciudad->id,
            'talleres_con_costo' => true,
        ]);

        $formType = FormType::factory()->create([
            'event_id' => $evento->id, 'cupo_total' => 100, 'activo' => true,
            'requiere_categoria' => true, 'costo_edicion' => 0,
        ]);

        $especialista = Category::factory()->create(['event_id' => $evento->id, 'name' => 'Especialista', 'price' => 50]);
        $licenciado = Category::factory()->create(['event_id' => $evento->id, 'name' => 'Licenciado', 'price' => 50]);

        $taller = Taller::factory()->create(['evento_id' => $evento->id, 'modalidad' => 'OPTIONAL', 'precio' => 80]);
        $sesion = SesionCongreso::factory()->create(['evento_id' => $evento->id, 'taller_id' => $taller->id, 'cupo' => 10]);

        // Período general (sin categoría) — aplica a cualquier categoría
        // SIN período propio.
        TallerPricePeriod::create([
            'taller_id' => $taller->id, 'category_id' => null,
            'nombre' => 'General', 'price' => 60,
            'fecha_desde' => Carbon::today()->subDays(5), 'fecha_hasta' => Carbon::today()->addDays(5),
        ]);
        // Período propio de Especialista — track exclusivo, Licenciado
        // nunca lo ve.
        TallerPricePeriod::create([
            'taller_id' => $taller->id, 'category_id' => $especialista->id,
            'nombre' => 'Especialista preventa', 'price' => 100,
            'fecha_desde' => Carbon::today()->subDays(5), 'fecha_hasta' => Carbon::today()->addDays(5),
        ]);

        $participanteData = fn (Category $categoria, string $doc) => [
            'nombre' => 'Ana', 'apellido' => 'Prueba', 'alias' => '', 'genero' => 'Femenino',
            'tipoDocumento' => 'DNI', 'numeroDocumento' => $doc,
            'polera' => '', 'precioPolera' => 0,
            'nacimiento' => ['dia' => 1, 'mes' => 1, 'anio' => 1995], 'edad' => 30,
            'correo' => 'ana' . rand(1, 999999) . '@test.net', 'direccion' => 'x', 'ciudad' => 'x', 'telefono' => '123',
            'contacto_emergencia' => ['nombre' => 'X', 'celular' => '123', 'relacion' => 'Madre'],
            'souvenirs' => [], 'answers' => [],
            'talleres' => [['taller_id' => $taller->id, 'sesion_congreso_id' => $sesion->id]],
            'categoria' => (string) $categoria->id, 'precioCategoria' => 50,
            'donacion' => 0, 'promoDescuento' => 0, 'promoCodigo' => '', 'subtotal' => 50,
        ];

        $registrationEspecialista = app(CrearInscripcionAction::class)->handle(RegistrationDTO::fromArray([
            'referencia' => 'LA-PERIODO-' . uniqid(),
            'fecha' => now()->toDateTimeString(),
            'evento_id' => $evento->id, 'evento_nombre' => $evento->nombre,
            'form_types_id' => $formType->id,
            'tipo_pago' => 'pendiente', 'pago_status' => 'pending', 'pay_order_number' => null,
            'totales' => ['inscripcion' => 50, 'donacion' => 0, 'souvenirs' => 0, 'talleres' => 100, 'fee' => 7.5, 'descuento' => 0, 'descuento_registrante' => 0, 'grand_total' => 157.5],
            'participantes' => [$participanteData($especialista, '30100001')],
        ]));

        $registrationLicenciado = app(CrearInscripcionAction::class)->handle(RegistrationDTO::fromArray([
            'referencia' => 'LA-PERIODO-' . uniqid(),
            'fecha' => now()->toDateTimeString(),
            'evento_id' => $evento->id, 'evento_nombre' => $evento->nombre,
            'form_types_id' => $formType->id,
            'tipo_pago' => 'pendiente', 'pago_status' => 'pending', 'pay_order_number' => null,
            'totales' => ['inscripcion' => 50, 'donacion' => 0, 'souvenirs' => 0, 'talleres' => 60, 'fee' => 5.5, 'descuento' => 0, 'descuento_registrante' => 0, 'grand_total' => 115.5],
            'participantes' => [$participanteData($licenciado, '30100002')],
        ]));

        $this->assertEquals(100.0, $registrationEspecialista->participants()->first()->talleresSesiones->first()->unit_price);
        $this->assertEquals(60.0, $registrationLicenciado->participants()->first()->talleresSesiones->first()->unit_price);
    }
}

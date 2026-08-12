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
use App\Models\SubtipoEvento;
use App\Models\TipoEvento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Cierre de la brecha de revalidación del cargo de servicio — ver
 * PRD-cargo-servicio-por-evento.md. Antes `CrearInscripcionAction`
 * guardaba `totals.fee` tal cual lo mandaba el cliente, sin recalcularlo
 * contra `evento.fee_pct` (mismo patrón que la brecha de
 * `precioCategoria` documentada en el PRD de precios por período).
 */
class CrearInscripcionFeePctTest extends TestCase
{
    use RefreshDatabase;

    private Evento $evento;

    private FormType $formType;

    private Category $categoria;

    protected function setUp(): void
    {
        parent::setUp();

        // notificarInscripcionPendiente() manda un email síncrono al
        // crear — este proyecto no tiene sandbox de email por defecto.
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
            // Precios por período (12/08/2026) — pinneado a true, ver
            // PRD-precios-periodos-fechas.md sección 0.
            'requiere_categoria' => true,
        ]);
        $this->categoria = Category::factory()->create([
            'event_id' => $this->evento->id,
            'price' => 100,
        ]);
    }

    private function dtoConFee(float $fee): RegistrationDTO
    {
        $participante = [
            'nombre' => 'Ana', 'apellido' => 'Prueba', 'alias' => '', 'genero' => 'Femenino',
            'tipoDocumento' => 'DNI', 'numeroDocumento' => (string) rand(10000000, 99999999),
            'polera' => '', 'precioPolera' => 0,
            'nacimiento' => ['dia' => 1, 'mes' => 1, 'anio' => 1995], 'edad' => 30,
            'correo' => 'ana'.rand(1, 999999).'@test.net', 'direccion' => 'x', 'ciudad' => 'x', 'telefono' => '123',
            'contacto_emergencia' => ['nombre' => 'X', 'celular' => '123', 'relacion' => 'Madre'],
            'souvenirs' => [], 'answers' => [],
            'categoria' => (string) $this->categoria->id, 'precioCategoria' => 100, 'donacion' => 0, 'promoDescuento' => 0, 'promoCodigo' => '',
            'subtotal' => 100 + $fee,
        ];

        return RegistrationDTO::fromArray([
            'referencia' => 'LA-FEE-'.uniqid(),
            'fecha' => now()->toDateTimeString(),
            'evento_id' => $this->evento->id,
            'evento_nombre' => $this->evento->nombre,
            'form_types_id' => $this->formType->id,
            'tipo_pago' => 'pendiente',
            'pago_status' => 'pending',
            'pay_order_number' => null,
            'totales' => [
                'inscripcion' => 100, 'donacion' => 0, 'souvenirs' => 0, 'fee' => $fee,
                'descuento' => 0, 'descuento_registrante' => 0, 'grand_total' => 100 + $fee,
            ],
            'participantes' => [$participante],
        ]);
    }

    public function test_acepta_el_fee_correcto_con_el_5_por_ciento_default(): void
    {
        $registration = app(CrearInscripcionAction::class)->handle($this->dtoConFee(5.00));

        $this->assertDatabaseHas('registrations', ['id' => $registration->id]);
    }

    public function test_rechaza_un_fee_que_no_coincide_con_el_5_por_ciento_default(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('cargo de servicio no coincide');

        app(CrearInscripcionAction::class)->handle($this->dtoConFee(0.00));
    }

    public function test_acepta_el_fee_correcto_con_un_porcentaje_personalizado(): void
    {
        $this->evento->update(['fee_pct' => 0.10]);

        $registration = app(CrearInscripcionAction::class)->handle($this->dtoConFee(10.00));

        $this->assertDatabaseHas('registrations', ['id' => $registration->id]);
    }

    public function test_rechaza_el_fee_del_5_por_ciento_si_el_evento_ya_tiene_otro_porcentaje(): void
    {
        $this->evento->update(['fee_pct' => 0.10]);

        $this->expectException(\DomainException::class);

        app(CrearInscripcionAction::class)->handle($this->dtoConFee(5.00));
    }
}

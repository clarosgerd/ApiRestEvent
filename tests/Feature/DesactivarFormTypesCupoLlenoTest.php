<?php

namespace Tests\Feature;

use App\Models\Evento;
use App\Models\FormType;
use App\Models\Participante;
use App\Models\Registration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Comando `form_types:desactivar-cupo-lleno` — App\Actions\SweepFormTypesCupoLlenoAction.
 * Tampoco tenía ningún test automatizado antes de esta suite.
 */
class DesactivarFormTypesCupoLlenoTest extends TestCase
{
    use RefreshDatabase;

    private function crearInscripcionVigente(FormType $formType): void
    {
        $registration = Registration::create([
            'referencia'    => 'REF-CUPO-' . uniqid(),
            'fecha'         => now(),
            'evento_id'     => $formType->event_id,
            'form_types_id' => $formType->id,
            'evento_nombre' => 'x',
            'tipo_pago'     => 'qr',
            'pago_status'   => 'paid',
        ]);

        Participante::create([
            'registration_id'  => $registration->id,
            'nombre'            => 'x',
            'apellido'          => 'x',
            'alias'             => '',
            'genero'            => 'Masculino',
            'tipo_documento'    => 'CI',
            'numero_documento'  => (string) uniqid(),
            'fecha_nacimiento'  => '1990-01-01',
            'edad'              => 36,
            'correo'            => uniqid() . '@example.net',
            'direccion'         => 'x',
            'ciudad'            => 'x',
            'telefono'          => 'x',
            'categoria'         => '1',
            'precio_categoria'  => 0,
            'donacion'          => 0,
            'promo_descuento'   => 0,
            'promo_codigo'      => '',
            'subtotal'          => 0,
        ]);
    }

    public function test_deactivates_form_type_when_cupo_is_full(): void
    {
        $evento = Evento::factory()->create();
        $formType = FormType::factory()->create([
            'event_id'   => $evento->id,
            'cupo_total' => 1,
            'activo'     => true,
        ]);
        $this->crearInscripcionVigente($formType);

        $this->artisan('form_types:desactivar-cupo-lleno')
            ->expectsOutput('Form types desactivados: 1')
            ->assertExitCode(0);

        $this->assertFalse((bool) $formType->fresh()->activo);
    }

    public function test_does_not_deactivate_form_type_under_cupo(): void
    {
        $evento = Evento::factory()->create();
        $formType = FormType::factory()->create([
            'event_id'   => $evento->id,
            'cupo_total' => 5,
            'activo'     => true,
        ]);
        $this->crearInscripcionVigente($formType);

        $this->artisan('form_types:desactivar-cupo-lleno')
            ->expectsOutput('Form types desactivados: 0')
            ->assertExitCode(0);

        $this->assertTrue((bool) $formType->fresh()->activo);
    }

    public function test_ignores_cancelled_registrations_when_counting_cupo(): void
    {
        $evento = Evento::factory()->create();
        $formType = FormType::factory()->create([
            'event_id'   => $evento->id,
            'cupo_total' => 1,
            'activo'     => true,
        ]);
        // Mismo criterio que el chequeo "en caliente" — cancelled/failed no
        // cuenta como cupo ocupado (ver deactivateFormTypeIfCupoLleno()).
        $registration = Registration::create([
            'referencia'    => 'REF-CUPO-CANCELLED',
            'fecha'         => now(),
            'evento_id'     => $evento->id,
            'form_types_id' => $formType->id,
            'evento_nombre' => 'x',
            'tipo_pago'     => 'qr',
            'pago_status'   => 'cancelled',
        ]);
        Participante::create([
            'registration_id'  => $registration->id,
            'nombre'            => 'x',
            'apellido'          => 'x',
            'alias'             => '',
            'genero'            => 'Masculino',
            'tipo_documento'    => 'CI',
            'numero_documento'  => '1234',
            'fecha_nacimiento'  => '1990-01-01',
            'edad'              => 36,
            'correo'            => 'cancelled@example.net',
            'direccion'         => 'x',
            'ciudad'            => 'x',
            'telefono'          => 'x',
            'categoria'         => '1',
            'precio_categoria'  => 0,
            'donacion'          => 0,
            'promo_descuento'   => 0,
            'promo_codigo'      => '',
            'subtotal'          => 0,
        ]);

        $this->artisan('form_types:desactivar-cupo-lleno')
            ->expectsOutput('Form types desactivados: 0')
            ->assertExitCode(0);

        $this->assertTrue((bool) $formType->fresh()->activo);
    }
}

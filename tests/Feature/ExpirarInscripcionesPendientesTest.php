<?php

namespace Tests\Feature;

use App\Models\Evento;
use App\Models\FormType;
use App\Models\Participante;
use App\Models\Registration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Comando `notificaciones:expirar-pendientes` — App\Actions\ExpirarInscripcionesPendientesAction.
 * Conecta `form_types.tiempo_expiracion_min`, que hasta el 09/08/2026 se
 * guardaba pero no se usaba en ningún lado (verificado antes de escribir
 * esto) — a pedido del usuario, para que un pending no "secuestre" cupo de
 * un evento lejano indefinidamente.
 */
class ExpirarInscripcionesPendientesTest extends TestCase
{
    use RefreshDatabase;

    private function crearRegistrationPendiente(FormType $formType, int $minutosDeAntiguedad, string $tipoPago = 'qr'): Registration
    {
        $registration = Registration::create([
            'referencia'    => 'REF-EXP-' . uniqid(),
            'fecha'         => now(),
            'evento_id'     => $formType->event_id,
            'form_types_id' => $formType->id,
            'evento_nombre' => 'x',
            'tipo_pago'     => $tipoPago,
            'pago_status'   => 'pending',
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

        // created_at no está en $fillable — update() de Eloquent lo
        // ignoraría silenciosamente, por eso el UPDATE directo por query
        // builder (bypassea mass-assignment) para simular antigüedad.
        DB::table('registrations')
            ->where('id', $registration->id)
            ->update(['created_at' => now()->subMinutes($minutosDeAntiguedad)]);

        return $registration->fresh();
    }

    public function test_expires_pending_registration_past_tiempo_expiracion(): void
    {
        Mail::fake();
        $evento = Evento::factory()->create();
        $formType = FormType::factory()->create([
            'event_id' => $evento->id,
            'tiempo_expiracion_min' => 30,
        ]);
        $registration = $this->crearRegistrationPendiente($formType, 40);

        $this->artisan('notificaciones:expirar-pendientes')
            ->expectsOutput('Inscripciones expiradas: 1')
            ->assertExitCode(0);

        $this->assertSame('cancelled', $registration->fresh()->pago_status);
    }

    public function test_does_not_expire_registration_within_tiempo_expiracion(): void
    {
        $evento = Evento::factory()->create();
        $formType = FormType::factory()->create([
            'event_id' => $evento->id,
            'tiempo_expiracion_min' => 30,
        ]);
        $registration = $this->crearRegistrationPendiente($formType, 10);

        $this->artisan('notificaciones:expirar-pendientes')
            ->expectsOutput('Inscripciones expiradas: 0')
            ->assertExitCode(0);

        $this->assertSame('pending', $registration->fresh()->pago_status);
    }

    public function test_does_not_expire_when_tiempo_expiracion_is_zero(): void
    {
        $evento = Evento::factory()->create();
        $formType = FormType::factory()->create([
            'event_id' => $evento->id,
            'tiempo_expiracion_min' => 0,
        ]);
        // Aunque tenga meses de antigüedad, 0 = sin expiración configurada.
        $registration = $this->crearRegistrationPendiente($formType, 60 * 24 * 90);

        $this->artisan('notificaciones:expirar-pendientes')
            ->expectsOutput('Inscripciones expiradas: 0')
            ->assertExitCode(0);

        $this->assertSame('pending', $registration->fresh()->pago_status);
    }

    /**
     * Bug real de producción (19/08/2026) — `Carbon::rawAddUnit(): Argument
     * #3 ($value) must be of type int|float, string given` en
     * addMinutes(), porque `tiempo_expiracion_min` no tenía cast en
     * FormType y en el hosting real PDO devuelve columnas numéricas como
     * string (mismo driver quirk ya documentado antes con `evento_id`).
     * No se puede reproducir el driver real en este entorno (localmente
     * PDO ya devuelve int) — `setRawAttributes()` simula exactamente lo
     * que Eloquent recibe antes de aplicar el cast, sin importar el
     * driver, para probar que `FormType::$casts` lo convierte a int igual.
     */
    public function test_formtype_castea_tiempo_expiracion_min_a_int_aunque_llegue_como_string(): void
    {
        $formType = new FormType();
        $formType->setRawAttributes(['tiempo_expiracion_min' => '30'], true);

        $this->assertIsInt($formType->tiempo_expiracion_min);
        $this->assertSame(30, $formType->tiempo_expiracion_min);
    }

    public function test_does_not_touch_already_paid_registration(): void
    {
        $evento = Evento::factory()->create();
        $formType = FormType::factory()->create([
            'event_id' => $evento->id,
            'tiempo_expiracion_min' => 30,
        ]);
        $registration = $this->crearRegistrationPendiente($formType, 90);
        $registration->update(['pago_status' => 'paid']);

        $this->artisan('notificaciones:expirar-pendientes')
            ->expectsOutput('Inscripciones expiradas: 0')
            ->assertExitCode(0);

        $this->assertSame('paid', $registration->fresh()->pago_status);
    }

    /**
     * Bug real (02/09/2026) — tipo_pago='pendiente' ("el participante deja
     * el registro guardado y paga después", sin pasarela, sin plazo corto
     * por diseño) recibía el mismo tiempo_expiracion_min que un QR/pasarela
     * abandonado. 14 inscripciones de una carga masiva CSV (siempre
     * tipo_pago='pendiente') amanecieron canceladas por esto.
     */
    public function test_no_expira_tipo_pago_pendiente_aunque_supere_el_tiempo(): void
    {
        $evento = Evento::factory()->create();
        $formType = FormType::factory()->create([
            'event_id' => $evento->id,
            'tiempo_expiracion_min' => 30,
        ]);
        $registration = $this->crearRegistrationPendiente($formType, 90, tipoPago: 'pendiente');

        $this->artisan('notificaciones:expirar-pendientes')
            ->expectsOutput('Inscripciones expiradas: 0')
            ->assertExitCode(0);

        $this->assertSame('pending', $registration->fresh()->pago_status);
    }
}

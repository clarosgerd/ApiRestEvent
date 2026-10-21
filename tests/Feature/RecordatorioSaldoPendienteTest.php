<?php

namespace Tests\Feature;

use App\Mail\RecordatorioSaldoPendienteMail;
use App\Models\Ciudad;
use App\Models\Evento;
use App\Models\FormType;
use App\Models\Organizador;
use App\Models\Pais;
use App\Models\Participante;
use App\Models\Registration;
use App\Models\SubtipoEvento;
use App\Models\TipoEvento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Reserva de cupo con seña (22/10/2026, pedido real del usuario) —
 * recordatorio de saldo pendiente un día antes de la fecha límite de pago.
 */
class RecordatorioSaldoPendienteTest extends TestCase
{
    use RefreshDatabase;

    private FormType $formType;

    private Organizador $organizador;

    private TipoEvento $tipoEvento;

    private SubtipoEvento $subtipoEvento;

    private Pais $pais;

    private Ciudad $ciudad;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pais = Pais::factory()->create();
        $this->ciudad = Ciudad::factory()->create(['pais_id' => $this->pais->id]);
        $this->organizador = Organizador::factory()->create();
        $this->tipoEvento = TipoEvento::factory()->create();
        $this->subtipoEvento = SubtipoEvento::factory()->create(['tipo_evento_id' => $this->tipoEvento->id]);
    }

    private function eventoConFechaLimite(?string $fechaLimite, string $fechaInicio): Evento
    {
        return Evento::factory()->create([
            'organizador_id' => $this->organizador->id, 'tipo_evento_id' => $this->tipoEvento->id,
            'subtipo_evento_id' => $this->subtipoEvento->id, 'pais_id' => $this->pais->id, 'ciudad_id' => $this->ciudad->id,
            'fecha_inicio' => $fechaInicio,
            'fecha_limite_pago_reserva' => $fechaLimite,
        ]);
    }

    private function inscripcionPagadaConSaldo(Evento $evento, float $saldoPendiente): Registration
    {
        $formType = FormType::factory()->create(['event_id' => $evento->id, 'requiere_categoria' => false]);

        $registration = Registration::factory()->create([
            'evento_id' => $evento->id, 'form_types_id' => $formType->id,
            'referencia' => 'LA-SALDO-' . uniqid(), 'fecha' => now(), 'evento_nombre' => $evento->nombre,
            'tipo_pago' => 'sip', 'pago_status' => 'paid', 'saldo_pendiente' => $saldoPendiente,
        ]);

        Participante::create([
            'registration_id' => $registration->id,
            'nombre' => 'Ana', 'apellido' => 'Prueba', 'genero' => 'Femenino',
            'tipo_documento' => 'DNI', 'numero_documento' => (string) rand(10000000, 99999999),
            'fecha_nacimiento' => '1990-01-01', 'edad' => 34, 'correo' => 'ana' . rand(1, 99999) . '@test.net',
            'direccion' => 'x', 'ciudad' => 'x', 'telefono' => '123',
            'categoria' => 'General', 'subtotal' => 100,
        ]);

        return $registration;
    }

    public function test_no_envia_si_no_hay_saldo_pendiente(): void
    {
        Mail::fake();
        $evento = $this->eventoConFechaLimite(now()->addDay()->toDateString(), now()->addDays(10)->toDateString());
        $this->inscripcionPagadaConSaldo($evento, 0);

        $this->artisan('notificaciones:recordatorio-saldo-pendiente')->assertSuccessful();

        Mail::assertNotSent(RecordatorioSaldoPendienteMail::class);
    }

    public function test_envia_un_dia_antes_de_la_fecha_limite_configurada(): void
    {
        Mail::fake();
        $evento = $this->eventoConFechaLimite(now()->addDay()->toDateString(), now()->addDays(10)->toDateString());
        $this->inscripcionPagadaConSaldo($evento, 150);

        $this->artisan('notificaciones:recordatorio-saldo-pendiente')->assertSuccessful();

        Mail::assertSent(RecordatorioSaldoPendienteMail::class, 1);
    }

    public function test_no_envia_si_faltan_mas_de_un_dia(): void
    {
        Mail::fake();
        $evento = $this->eventoConFechaLimite(now()->addDays(5)->toDateString(), now()->addDays(20)->toDateString());
        $this->inscripcionPagadaConSaldo($evento, 150);

        $this->artisan('notificaciones:recordatorio-saldo-pendiente')->assertSuccessful();

        Mail::assertNotSent(RecordatorioSaldoPendienteMail::class);
    }

    public function test_envia_si_la_fecha_limite_ya_paso(): void
    {
        Mail::fake();
        $evento = $this->eventoConFechaLimite(now()->subDay()->toDateString(), now()->addDays(2)->toDateString());
        $this->inscripcionPagadaConSaldo($evento, 150);

        $this->artisan('notificaciones:recordatorio-saldo-pendiente')->assertSuccessful();

        Mail::assertSent(RecordatorioSaldoPendienteMail::class, 1);
    }

    public function test_sin_fecha_limite_configurada_cae_a_la_fecha_del_evento(): void
    {
        Mail::fake();
        $evento = $this->eventoConFechaLimite(null, now()->addDay()->toDateString());
        $this->inscripcionPagadaConSaldo($evento, 150);

        $this->artisan('notificaciones:recordatorio-saldo-pendiente')->assertSuccessful();

        Mail::assertSent(RecordatorioSaldoPendienteMail::class, 1);
    }

    public function test_no_duplica_el_envio_en_una_segunda_corrida(): void
    {
        Mail::fake();
        $evento = $this->eventoConFechaLimite(now()->addDay()->toDateString(), now()->addDays(10)->toDateString());
        $this->inscripcionPagadaConSaldo($evento, 150);

        $this->artisan('notificaciones:recordatorio-saldo-pendiente')->assertSuccessful();
        $this->artisan('notificaciones:recordatorio-saldo-pendiente')->assertSuccessful();

        Mail::assertSent(RecordatorioSaldoPendienteMail::class, 1);
    }
}

<?php

namespace Tests\Feature;

use App\Mail\CertificadoCongresoMail;
use App\Models\CertificadoCongresoEnviado;
use App\Models\Ciudad;
use App\Models\Evento;
use App\Models\Organizador;
use App\Models\Pais;
use App\Models\Participante;
use App\Models\Registration;
use App\Models\SesionCongreso;
use App\Models\SubtipoEvento;
use App\Models\TipoEvento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Certificados automáticos de congreso — ver
 * EnviarCertificadosCongresoAction y elascenso/event/brain/ (sesión
 * 11/08/2026). SIEMPRE Mail::fake() — este proyecto no tiene sandbox de
 * email, un test sin fake mandaría un correo real de verdad.
 */
class EnviarCertificadosCongresoTest extends TestCase
{
    use RefreshDatabase;

    private Evento $eventoCongresoCerrado;

    private Pais $pais;

    private Ciudad $ciudad;

    private Organizador $organizador;

    private SubtipoEvento $subtipoEvento;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->pais = Pais::factory()->create();
        $this->ciudad = Ciudad::factory()->create(['pais_id' => $this->pais->id]);
        $this->organizador = Organizador::factory()->create();

        $tipoCongreso = TipoEvento::factory()->create(['nombre' => 'Congreso / No aplica']);
        $this->subtipoEvento = SubtipoEvento::factory()->create(['tipo_evento_id' => $tipoCongreso->id]);

        $this->eventoCongresoCerrado = Evento::factory()->create([
            'organizador_id' => $this->organizador->id,
            'tipo_evento_id' => $tipoCongreso->id,
            'subtipo_evento_id' => $this->subtipoEvento->id,
            'pais_id' => $this->pais->id,
            'ciudad_id' => $this->ciudad->id,
            'estado_evento_id' => 'closed',
        ]);
    }

    private function crearParticipante(Evento $evento, array $overrides = []): Participante
    {
        $registration = Registration::factory()->create([
            'evento_id' => $evento->id,
            'form_types_id' => \App\Models\FormType::factory()->create(['event_id' => $evento->id])->id,
            'referencia' => 'LA-CERT-'.uniqid(),
            'fecha' => now(),
            'evento_nombre' => $evento->nombre,
            'tipo_pago' => 'pendiente',
            'pago_status' => 'paid',
        ]);

        return Participante::create(array_merge([
            'registration_id' => $registration->id,
            'nombre' => 'Ana', 'apellido' => 'Prueba', 'genero' => 'Femenino',
            'tipo_documento' => 'DNI', 'numero_documento' => (string) rand(10000000, 99999999),
            'fecha_nacimiento' => '1995-01-01', 'edad' => 30, 'correo' => 'ana'.rand(1, 99999).'@test.net',
            'direccion' => 'x', 'ciudad' => 'x', 'telefono' => '123',
            'categoria' => '1', 'subtotal' => 50,
        ], $overrides));
    }

    private function marcarAsistencia(SesionCongreso $sesion, Participante $participante): void
    {
        \App\Models\AsistenciaSesion::create([
            'sesion_congreso_id' => $sesion->id,
            'participante_id' => $participante->id,
            'checkin_at' => now(),
        ]);
    }

    public function test_envia_un_certificado_con_todas_las_sesiones_asistidas(): void
    {
        $sesion1 = SesionCongreso::factory()->create(['evento_id' => $this->eventoCongresoCerrado->id, 'titulo' => 'Keynote']);
        $sesion2 = SesionCongreso::factory()->create(['evento_id' => $this->eventoCongresoCerrado->id, 'titulo' => 'Taller']);
        $participante = $this->crearParticipante($this->eventoCongresoCerrado);
        $this->marcarAsistencia($sesion1, $participante);
        $this->marcarAsistencia($sesion2, $participante);

        Artisan::call('certificados:enviar-congreso');

        Mail::assertSent(CertificadoCongresoMail::class, function ($mail) use ($participante) {
            return $mail->hasTo($participante->correo) && $mail->sesiones->count() === 2;
        });
        $this->assertDatabaseHas('certificados_congreso_enviados', [
            'evento_id' => $this->eventoCongresoCerrado->id,
            'participante_id' => $participante->id,
        ]);
    }

    public function test_no_envia_a_participante_sin_correo(): void
    {
        $sesion = SesionCongreso::factory()->create(['evento_id' => $this->eventoCongresoCerrado->id]);
        $participante = $this->crearParticipante($this->eventoCongresoCerrado, ['correo' => '']);
        $this->marcarAsistencia($sesion, $participante);

        Artisan::call('certificados:enviar-congreso');

        Mail::assertNothingSent();
        $this->assertDatabaseCount('certificados_congreso_enviados', 0);
    }

    public function test_no_envia_si_el_evento_no_esta_cerrado(): void
    {
        $this->eventoCongresoCerrado->update(['estado_evento_id' => 'open']);
        $sesion = SesionCongreso::factory()->create(['evento_id' => $this->eventoCongresoCerrado->id]);
        $participante = $this->crearParticipante($this->eventoCongresoCerrado);
        $this->marcarAsistencia($sesion, $participante);

        Artisan::call('certificados:enviar-congreso');

        Mail::assertNothingSent();
    }

    public function test_no_envia_si_el_evento_cerrado_no_es_tipo_congreso(): void
    {
        $tipoCarrera = TipoEvento::factory()->create(['nombre' => 'Carrera de Ruta']);
        $subtipoCarrera = SubtipoEvento::factory()->create(['tipo_evento_id' => $tipoCarrera->id]);
        $eventoCarrera = Evento::factory()->create([
            'organizador_id' => $this->organizador->id,
            'tipo_evento_id' => $tipoCarrera->id,
            'subtipo_evento_id' => $subtipoCarrera->id,
            'pais_id' => $this->pais->id,
            'ciudad_id' => $this->ciudad->id,
            'estado_evento_id' => 'closed',
        ]);
        $sesion = SesionCongreso::factory()->create(['evento_id' => $eventoCarrera->id]);
        $participante = $this->crearParticipante($eventoCarrera);
        $this->marcarAsistencia($sesion, $participante);

        Artisan::call('certificados:enviar-congreso');

        Mail::assertNothingSent();
    }

    public function test_no_reenvia_al_correr_el_comando_dos_veces(): void
    {
        $sesion = SesionCongreso::factory()->create(['evento_id' => $this->eventoCongresoCerrado->id]);
        $participante = $this->crearParticipante($this->eventoCongresoCerrado);
        $this->marcarAsistencia($sesion, $participante);

        Artisan::call('certificados:enviar-congreso');
        Artisan::call('certificados:enviar-congreso');

        Mail::assertSent(CertificadoCongresoMail::class, 1);
        $this->assertDatabaseCount('certificados_congreso_enviados', 1);
    }

    public function test_un_fallo_de_envio_no_registra_idempotencia_para_reintentar(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP caído'));

        $sesion = SesionCongreso::factory()->create(['evento_id' => $this->eventoCongresoCerrado->id]);
        $participante = $this->crearParticipante($this->eventoCongresoCerrado);
        $this->marcarAsistencia($sesion, $participante);

        Artisan::call('certificados:enviar-congreso');

        $this->assertDatabaseCount('certificados_congreso_enviados', 0);
    }
}

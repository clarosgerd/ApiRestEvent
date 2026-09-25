<?php

namespace Tests\Feature;

use App\Actions\EnviarSeguimientoLeadAction;
use App\Mail\SeguimientoExpositorMail;
use App\Models\EmpresaExpositora;
use App\Models\Evento;
use App\Models\LeadCapturado;
use App\Models\Persona;
use App\Models\SeguimientoBaja;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Tests\Support\ArmaExpositores;
use Tests\TestCase;

/**
 * SmartStand fase 4 — correo de seguimiento automático de la empresa expositora.
 */
class SeguimientoExpositorTest extends TestCase
{
    use RefreshDatabase, ArmaExpositores;

    private function eventoConSeguimiento(array $config = []): Evento
    {
        return $this->crearEvento(['expositores_config' => array_merge([
            'seguimiento_habilitado'         => true,
            'seguimiento_tyc_confirmado_at'  => now()->toDateTimeString(),
        ], $config)]);
    }

    private function cuentaActiva(Evento $evento, array $extra = []): EmpresaExpositora
    {
        return $this->crearCuenta($evento, array_merge([
            'nombre'               => 'Farma Andina',
            'seguimiento_activo'   => true,
            'seguimiento_asunto'   => 'Gracias {nombre}',
            'seguimiento_mensaje'  => "Hola {nombre},\n\nGracias por visitar a {empresa}.",
            'seguimiento_reply_to' => 'ventas@farma.test',
        ], $extra));
    }

    private function capturar(EmpresaExpositora $cuenta, $asistente)
    {
        $this->comoExpositor($cuenta);

        return $this->postJson('/api/v1/expositor/leads', ['participante_id' => $asistente->id]);
    }

    public function test_al_capturar_se_envia_un_correo_con_reply_to_baja_y_texto_de_la_empresa(): void
    {
        Mail::fake();
        $evento = $this->eventoConSeguimiento();
        $cuenta = $this->cuentaActiva($evento);
        $asistente = $this->crearAsistente($evento, ['nombre' => 'Luis', 'correo' => 'Luis@Correo.test']);

        $this->capturar($cuenta, $asistente)->assertCreated();

        Mail::assertSent(SeguimientoExpositorMail::class, function (SeguimientoExpositorMail $mail) use ($cuenta) {
            $mail->build();
            $this->assertTrue($mail->hasTo('luis@correo.test'));
            $this->assertTrue($mail->hasReplyTo('ventas@farma.test'));
            $this->assertTrue($mail->hasFrom(config('mail.from.address'), 'Farma Andina vía Inscrito'));
            $this->assertSame('Gracias Luis', $mail->subject);
            $html = $mail->render();
            $this->assertStringContainsString('Gracias por visitar a Farma Andina', $html);
            $this->assertStringContainsString('/seguimiento/baja/', $html);
            $this->assertStringContainsString('signature=', $html);

            return true;
        });
        $lead = LeadCapturado::firstOrFail();
        $this->assertSame('enviado', $lead->seguimiento_estado);
        $this->assertNull($lead->seguimiento_motivo);
        $this->assertNotNull($lead->seguimiento_at);
    }

    public function test_re_escanear_no_reenvia(): void
    {
        Mail::fake();
        $evento = $this->eventoConSeguimiento();
        $cuenta = $this->cuentaActiva($evento);
        $asistente = $this->crearAsistente($evento);

        $this->capturar($cuenta, $asistente)->assertCreated();
        $this->capturar($cuenta, $asistente)->assertOk();

        Mail::assertSent(SeguimientoExpositorMail::class, 1);
    }

    public function test_no_envia_si_el_evento_o_la_empresa_no_lo_activaron(): void
    {
        Mail::fake();
        $apagadoEvento = $this->crearEvento();
        $c1 = $this->cuentaActiva($apagadoEvento);
        $this->capturar($c1, $this->crearAsistente($apagadoEvento))->assertCreated();

        $evento = $this->eventoConSeguimiento();
        $c2 = $this->cuentaActiva($evento, ['seguimiento_activo' => false]);
        $this->capturar($c2, $this->crearAsistente($evento))->assertCreated();

        Mail::assertNothingSent();
        $this->assertSame(0, LeadCapturado::whereNotNull('seguimiento_estado')->count());
    }

    public function test_el_texto_de_la_empresa_se_escapa_y_no_se_interpreta_como_html(): void
    {
        Mail::fake();
        $evento = $this->eventoConSeguimiento();
        // Se guarda directo (saltando la validación) para probar la defensa de la vista.
        $cuenta = $this->cuentaActiva($evento, ['seguimiento_mensaje' => '<script>alert(1)</script> <b>hola</b>']);
        $this->capturar($cuenta, $this->crearAsistente($evento))->assertCreated();

        Mail::assertSent(SeguimientoExpositorMail::class, function (SeguimientoExpositorMail $mail) {
            $mail->build();
            $html = $mail->render();
            $this->assertStringNotContainsString('<script>', $html);
            $this->assertStringContainsString('&lt;script&gt;', $html);

            return true;
        });
    }

    public function test_omite_con_motivo_sin_correo_baja_propia_baja_de_marketing_y_tope(): void
    {
        Mail::fake();
        $evento = $this->eventoConSeguimiento(['seguimiento_max_por_asistente' => 1]);
        $cuenta = $this->cuentaActiva($evento);
        $otra = $this->cuentaActiva($evento);

        $sinCorreo = $this->crearAsistente($evento, ['correo' => '']);
        $deBaja = $this->crearAsistente($evento, ['correo' => 'baja@correo.test']);
        SeguimientoBaja::create(['email' => 'baja@correo.test']);
        $marketing = $this->crearAsistente($evento, ['correo' => 'mkt@correo.test', 'numero_documento' => '555111']);
        Persona::factory()->create(['email' => 'mkt@correo.test', 'correo' => 'mkt@correo.test', 'acepta_marketing' => false]);
        $mismaDocumento = $this->crearAsistente($evento, ['correo' => 'otro-mail@correo.test', 'numero_documento' => '777222']);
        Persona::factory()->create(['email' => 'x@y.test', 'correo' => 'x@y.test', 'numero_documento' => '777222', 'acepta_marketing' => false]);
        $repetido = $this->crearAsistente($evento, ['correo' => 'tope@correo.test']);

        foreach ([$sinCorreo, $deBaja, $marketing, $mismaDocumento] as $a) {
            $this->capturar($cuenta, $a)->assertCreated();
        }
        $this->capturar($cuenta, $repetido)->assertCreated();   // 1.º: se envía
        $this->capturar($otra, $repetido)->assertCreated();     // 2.º: supera el tope de 1

        $estado = fn ($cuentaId, $asistente) => LeadCapturado::where('empresa_expositora_id', $cuentaId)
            ->where('participante_id', $asistente->id)->firstOrFail();

        $this->assertSame(['omitido', 'sin_correo'], [$estado($cuenta->id, $sinCorreo)->seguimiento_estado, $estado($cuenta->id, $sinCorreo)->seguimiento_motivo]);
        $this->assertSame('baja', $estado($cuenta->id, $deBaja)->seguimiento_motivo);
        $this->assertSame('baja', $estado($cuenta->id, $marketing)->seguimiento_motivo);
        $this->assertSame('baja', $estado($cuenta->id, $mismaDocumento)->seguimiento_motivo);
        $this->assertSame('enviado', $estado($cuenta->id, $repetido)->seguimiento_estado);
        $this->assertSame(['omitido', 'tope'], [$estado($otra->id, $repetido)->seguimiento_estado, $estado($otra->id, $repetido)->seguimiento_motivo]);
        Mail::assertSent(SeguimientoExpositorMail::class, 1);
    }

    public function test_el_link_de_baja_firmado_registra_el_correo_y_vale_para_todas_las_empresas(): void
    {
        Mail::fake();
        $evento = $this->eventoConSeguimiento();
        $cuenta = $this->cuentaActiva($evento);
        $otra = $this->cuentaActiva($evento);
        $asistente = $this->crearAsistente($evento, ['correo' => 'Doc@Correo.test']);
        $this->capturar($cuenta, $asistente)->assertCreated();
        $lead = LeadCapturado::firstOrFail();

        $this->get(URL::signedRoute('seguimiento.baja', ['lead' => $lead->id]))
            ->assertOk()
            ->assertSee('te diste de baja');

        $this->assertTrue(SeguimientoBaja::where('email', 'doc@correo.test')->exists());

        // Repetir el link no duplica.
        $this->get(URL::signedRoute('seguimiento.baja', ['lead' => $lead->id]))->assertOk();
        $this->assertSame(1, SeguimientoBaja::count());

        // El siguiente lead de ese correo, en otra empresa, queda omitido.
        $this->capturar($otra, $asistente)->assertCreated();
        $this->assertSame('baja', LeadCapturado::where('empresa_expositora_id', $otra->id)->firstOrFail()->seguimiento_motivo);
        Mail::assertSent(SeguimientoExpositorMail::class, 1);
    }

    public function test_la_baja_sin_firma_o_con_firma_alterada_es_403(): void
    {
        $evento = $this->eventoConSeguimiento();
        $lead = LeadCapturado::create([
            'empresa_expositora_id' => $this->cuentaActiva($evento)->id,
            'participante_id'       => $this->crearAsistente($evento)->id,
            'capturado_at'          => now(),
        ]);

        $this->get("/api/v1/seguimiento/baja/{$lead->id}")->assertForbidden();
        $this->get(URL::signedRoute('seguimiento.baja', ['lead' => $lead->id]) . 'x')->assertForbidden();
        $this->assertSame(0, SeguimientoBaja::count());
    }

    public function test_un_smtp_caido_deja_el_lead_fallido_sin_romper_la_captura_y_el_comando_reintenta(): void
    {
        $evento = $this->eventoConSeguimiento();
        $cuenta = $this->cuentaActiva($evento);
        $asistente = $this->crearAsistente($evento);

        Mail::extend('fallido', fn () => new class extends AbstractTransport {
            protected function doSend(SentMessage $message): void
            {
                throw new \RuntimeException('SMTP caído');
            }

            public function __toString(): string
            {
                return 'fallido';
            }
        });
        config(['mail.mailers.fallido' => ['transport' => 'fallido'], 'mail.default' => 'fallido']);

        $this->capturar($cuenta, $asistente)->assertCreated();   // la captura no se rompe

        $lead = LeadCapturado::firstOrFail();
        $this->assertSame('fallido', $lead->seguimiento_estado);
        $this->assertSame('error', $lead->seguimiento_motivo);
        $this->assertSame(1, $lead->seguimiento_intentos);

        // El SMTP vuelve.
        config(['mail.default' => 'array']);
        Mail::purge('fallido');
        Mail::purge('array');
        $this->artisan('expositores:reintentar-seguimientos')->assertSuccessful();

        $lead->refresh();
        $this->assertSame('enviado', $lead->seguimiento_estado);
        $this->assertSame(2, $lead->seguimiento_intentos);
    }

    public function test_el_reintento_ignora_fallos_viejos_y_los_que_ya_llegaron_a_3_intentos(): void
    {
        Mail::fake();
        $evento = $this->eventoConSeguimiento();
        $cuenta = $this->cuentaActiva($evento);
        $crear = fn (array $estado) => LeadCapturado::create(array_merge([
            'empresa_expositora_id' => $cuenta->id,
            'participante_id'       => $this->crearAsistente($evento)->id,
            'capturado_at'          => now(),
            'seguimiento_estado'    => 'fallido',
            'seguimiento_motivo'    => 'error',
            'seguimiento_intentos'  => 1,
            'seguimiento_at'        => now()->subHour(),
        ], $estado));

        $reciente = $crear([]);
        $viejo = $crear(['seguimiento_at' => now()->subHours(72)]);
        $agotado = $crear(['seguimiento_intentos' => EnviarSeguimientoLeadAction::MAX_INTENTOS]);

        $this->artisan('expositores:reintentar-seguimientos')->assertSuccessful();

        $this->assertSame('enviado', $reciente->fresh()->seguimiento_estado);
        $this->assertSame('fallido', $viejo->fresh()->seguimiento_estado);
        $this->assertSame('fallido', $agotado->fresh()->seguimiento_estado);
        Mail::assertSent(SeguimientoExpositorMail::class, 1);
    }

    // ── Ajustes de la empresa (GET/PUT /expositor/seguimiento) ──────────────

    public function test_get_devuelve_ajustes_contadores_y_si_el_evento_lo_habilito(): void
    {
        $cuenta = $this->cuentaActiva($this->crearEvento());   // el evento NO lo habilitó
        LeadCapturado::create([
            'empresa_expositora_id' => $cuenta->id,
            'participante_id'       => $this->crearAsistente($cuenta->evento)->id,
            'capturado_at'          => now(),
            'seguimiento_estado'    => 'enviado',
        ]);
        $this->comoExpositor($cuenta);

        $this->getJson('/api/v1/expositor/seguimiento')
            ->assertOk()
            ->assertJsonPath('data.habilitadoPorEvento', false)
            ->assertJsonPath('data.activo', true)
            ->assertJsonPath('data.replyTo', 'ventas@farma.test')
            ->assertJsonPath('data.contadores.enviados', 1)
            ->assertJsonPath('data.contadores.fallidos', 0)
            ->assertJsonPath('data.porDefecto.asunto', SeguimientoExpositorMail::ASUNTO_DEFAULT);
    }

    public function test_put_guarda_los_ajustes_y_vacios_quedan_en_null(): void
    {
        $cuenta = $this->crearCuenta($this->eventoConSeguimiento());
        $this->comoExpositor($cuenta);

        $this->putJson('/api/v1/expositor/seguimiento', [
            'activo'   => true,
            'asunto'   => 'Un gusto',
            'mensaje'  => 'Gracias por pasar.',
            'url'      => 'https://farma.test/catalogo',
            'reply_to' => 'contacto@farma.test',
        ])->assertOk()->assertJsonPath('data.activo', true)->assertJsonPath('data.habilitadoPorEvento', true);

        $cuenta->refresh();
        $this->assertTrue($cuenta->seguimiento_activo);
        $this->assertSame('https://farma.test/catalogo', $cuenta->seguimiento_url);

        $this->putJson('/api/v1/expositor/seguimiento', ['activo' => false, 'asunto' => '  ', 'mensaje' => '', 'url' => '', 'reply_to' => ''])
            ->assertOk();
        $cuenta->refresh();
        $this->assertFalse($cuenta->seguimiento_activo);
        $this->assertNull($cuenta->seguimiento_asunto);
        $this->assertNull($cuenta->seguimiento_mensaje);
        $this->assertNull($cuenta->seguimiento_url);
    }

    #[DataProvider('textosRechazados')]
    public function test_put_rechaza_links_correos_y_html_en_asunto_y_mensaje(string $texto): void
    {
        $cuenta = $this->crearCuenta($this->eventoConSeguimiento());
        $this->comoExpositor($cuenta);

        $this->putJson('/api/v1/expositor/seguimiento', ['activo' => true, 'mensaje' => $texto])
            ->assertStatus(422)->assertJsonValidationErrors('mensaje');
        $this->putJson('/api/v1/expositor/seguimiento', ['activo' => true, 'asunto' => $texto])
            ->assertStatus(422)->assertJsonValidationErrors('asunto');
    }

    public static function textosRechazados(): array
    {
        return [
            'https'      => ['Entra a https://malo.test ahora'],
            'http'       => ['visita http://malo.test'],
            'www'        => ['mira www.malo.test'],
            'dominio'    => ['ingresa a malo.com para ganar'],
            'sin esquema' => ['ve a //malo.test'],
            'correo'     => ['escríbeme a alguien@malo.test'],
            'html'       => ['<b>hola</b>'],
            'script'     => ['<script>alert(1)</script>'],
            'ángulo'     => ['a < b'],
        ];
    }

    public function test_put_valida_url_https_reply_to_y_largos(): void
    {
        $cuenta = $this->crearCuenta($this->eventoConSeguimiento());
        $this->comoExpositor($cuenta);

        $this->putJson('/api/v1/expositor/seguimiento', ['activo' => true, 'url' => 'http://inseguro.test'])
            ->assertStatus(422)->assertJsonValidationErrors('url');
        $this->putJson('/api/v1/expositor/seguimiento', ['activo' => true, 'url' => 'javascript:alert(1)'])
            ->assertStatus(422)->assertJsonValidationErrors('url');
        $this->putJson('/api/v1/expositor/seguimiento', ['activo' => true, 'reply_to' => 'no-es-correo'])
            ->assertStatus(422)->assertJsonValidationErrors('reply_to');
        $this->putJson('/api/v1/expositor/seguimiento', ['activo' => true, 'mensaje' => str_repeat('a', 1501)])
            ->assertStatus(422)->assertJsonValidationErrors('mensaje');
        $this->putJson('/api/v1/expositor/seguimiento', ['activo' => true, 'asunto' => str_repeat('a', 151)])
            ->assertStatus(422)->assertJsonValidationErrors('asunto');
        $this->putJson('/api/v1/expositor/seguimiento', [])
            ->assertStatus(422)->assertJsonValidationErrors('activo');
    }

    public function test_los_ajustes_exigen_sesion_de_expositor(): void
    {
        $this->getJson('/api/v1/expositor/seguimiento')->assertUnauthorized();
        $this->putJson('/api/v1/expositor/seguimiento', ['activo' => true])->assertUnauthorized();

        $this->actingAsAdmin();
        $this->getJson('/api/v1/expositor/seguimiento')->assertUnauthorized();
    }

    public function test_un_asunto_con_saltos_de_linea_no_inyecta_encabezados(): void
    {
        Mail::fake();
        $evento = $this->eventoConSeguimiento();
        $cuenta = $this->cuentaActiva($evento, ['seguimiento_asunto' => "Hola\r\nBcc: victima@x.test"]);
        $this->capturar($cuenta, $this->crearAsistente($evento))->assertCreated();

        Mail::assertSent(SeguimientoExpositorMail::class, function (SeguimientoExpositorMail $mail) {
            $mail->build();
            $this->assertStringNotContainsString("\n", $mail->subject);
            $this->assertStringNotContainsString("\r", $mail->subject);

            return true;
        });
    }

    // ── Validación de la config del evento (UpdateEventosRequest) ────────────

    public function test_el_organizador_no_puede_habilitar_el_seguimiento_sin_confirmar_los_terminos(): void
    {
        $evento = $this->crearEvento();
        $this->actingAsAdmin();

        $sin = $this->putJson("/api/v1/event/{$evento->id}", [
            'expositoresConfig' => ['seguimiento_habilitado' => true],
        ]);
        $sin->assertStatus(422)->assertJsonValidationErrors('expositoresConfig.seguimiento_habilitado');

        $con = $this->putJson("/api/v1/event/{$evento->id}", [
            'expositoresConfig' => [
                'seguimiento_habilitado'        => true,
                'seguimiento_tyc_confirmado_at' => '2026-09-26 10:00:00',
                'seguimiento_max_por_asistente' => 5,
                'especialidad_pregunta'         => 'especialidad',
                'pasaporte_min_stands'          => 3,
            ],
        ]);
        $con->assertOk();
        $config = $evento->fresh()->expositores_config;
        $this->assertTrue($config['seguimiento_habilitado']);
        $this->assertSame(5, $config['seguimiento_max_por_asistente']);
        $this->assertSame('especialidad', $config['especialidad_pregunta']);

        $this->putJson("/api/v1/event/{$evento->id}", ['expositoresConfig' => ['pasaporte_min_stands' => 0]])
            ->assertStatus(422);
        $this->putJson("/api/v1/event/{$evento->id}", ['expositoresConfig' => ['seguimiento_max_por_asistente' => 101]])
            ->assertStatus(422);
    }
}

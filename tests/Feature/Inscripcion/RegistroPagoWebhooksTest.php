<?php

namespace Tests\Feature\Inscripcion;

use App\Models\Category;
use App\Models\Evento;
use App\Models\FormasPago;
use App\Models\FormType;
use App\Models\Persona;
use App\Models\Registration;
use App\Services\QrProviderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Consolidación monolito (22/08/2026), Fase 2b — reemplaza
 * `elascenso-blade\tests\Feature\{Registro,Pago,Webhooks}\*`. No son tests
 * de paridad HTTP-mockeada como el original (`Http::fake()` sobre
 * `ApiRestEventClient`) — acá se prueba contra la BD real (`event_testing`,
 * `RefreshDatabase`), igual que el resto de la suite de `ApiRestEvent`,
 * porque ya no hay ningún salto de red que doblar para el flujo de
 * registro/pago. `QrProviderService` sí se dobla en los tests de Webhooks
 * (mismo motivo que el original: evita depender del SDK real de
 * sip-payment-integration/multipago-payment-integration, que sí existen en
 * disco como carpetas hermanas y se cargarían de verdad si no se doblara).
 */
class RegistroPagoWebhooksTest extends TestCase
{
    use RefreshDatabase;

    /**
     * `SipCallbackController`/`MultipagoCallbackController` instancian
     * `SipPayment\Support\Logger`/`SipPayment\Sip\CallbackAuthenticator`/
     * `MultipagoPayment\...` ellos mismos, directo (no a través de
     * `QrProviderService`) — al doblar `QrProviderService` en los tests de
     * Webhooks (evita depender del SDK real/su .env propio), el `require
     * $bootstrap` que normalmente registra el autoloader manual del SDK
     * nunca corre, así que esas clases quedan sin cargar. Mismo fix que ya
     * usaba `elascenso-blade\tests\Helpers.php`: requerir los archivos
     * puntuales acá (I/O de archivo plano, sin red, sin .env) antes de que
     * el controller los use.
     */
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            __DIR__.'/../../../../elascenso/event/sip-payment-integration/src/Support/Logger.php',
            __DIR__.'/../../../../elascenso/event/multipago-payment-integration/src/Support/Logger.php',
            __DIR__.'/../../../../elascenso/event/sip-payment-integration/src/Sip/CallbackAuthenticator.php',
            __DIR__.'/../../../../elascenso/event/multipago-payment-integration/src/Multipago/MultipagoException.php',
        ] as $sdkClassFile) {
            if (file_exists($sdkClassFile)) {
                require_once $sdkClassFile;
            }
        }
    }

    private function crearEventoConFormaDePago(array $formTypeOverrides = []): array
    {
        // 'tipo' solo admite 'integrado'/'manual' (enum real de la BD) — a
        // diferencia de la fixture HTTP-mockeada de elascenso-blade
        // (fixtureEvento(), que usaba 'no_integrado' porque nunca tocaba la
        // BD real), acá sí importa: 'manual' = pago sin gateway detrás
        // (mismo comportamiento que necesita tipoPago='pendiente').
        FormasPago::factory()->create([
            'slug' => 'pendiente', 'tipo' => 'manual', 'pasarela' => null,
            'organizador_id' => null, 'activo' => true,
        ]);

        $evento = Evento::factory()->create();
        $formType = FormType::factory()->create(array_merge([
            'event_id' => $evento->id,
            'requiere_categoria' => true,
            'requiere_contacto_emergencia' => false,
            'has_donation' => true,
            'has_promo_code' => true,
        ], $formTypeOverrides));
        $categoria = Category::factory()->create(['event_id' => $evento->id, 'price' => 80.0]);

        return [$evento, $formType, $categoria];
    }

    private function actingComoPersona(): array
    {
        $persona = Persona::factory()->create();
        $token = $persona->createToken('test-token')->plainTextToken;

        return [$persona, $token];
    }

    private function participantePayload(Category $categoria, array $overrides = []): array
    {
        return array_merge([
            'nombre' => 'Ana', 'apellido' => 'García', 'alias' => 'anita',
            'genero' => 'Femenino', 'correo' => 'ana@example.com',
            'direccion' => 'Av. Siempre Viva 123', 'ciudad' => 'La Paz',
            'telefono' => '22001122',
            'categoria' => $categoria->id, 'precioCategoria' => 80.0,
            'donacion' => 0, 'promoDescuento' => 0, 'promoCodigo' => '',
            'polera' => 'No shirt', 'precioPolera' => 0,
            'nacimiento' => ['dia' => 10, 'mes' => 5, 'anio' => 1995], 'edad' => 31,
            'numeroDocumento' => '87654321', 'subtotal' => 80.0,
            // UpdateRegistrationRequest::withValidator() exige contacto de
            // emergencia por defecto cuando no puede resolver el form_type
            // de la inscripción (ej. referencia inexistente) — se manda
            // siempre presente acá para no toparse con ese 422 en los tests
            // que no están probando esa validación puntual.
            'contacto_emergencia' => ['nombre' => 'Luis García', 'celular' => '099111111', 'relacion' => 'Padre'],
        ], $overrides);
    }

    // ── store() ──────────────────────────────────────────────

    public function test_store_crea_una_inscripcion_real_via_crearinscripcionaction(): void
    {
        [$evento, $formType, $categoria] = $this->crearEventoConFormaDePago();
        [, $token] = $this->actingComoPersona();

        $this->postJson('/registro', [
            'evento_id' => $evento->id,
            'form_type_id' => $formType->id,
            'tipoPago' => 'pendiente',
            'auth_token' => $token,
            'participantes' => [$this->participantePayload($categoria)],
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('totales.inscripcion', 80)
            ->assertJsonPath('totales.fee', 4)
            ->assertJsonPath('totales.grand_total', 84);

        $this->assertDatabaseCount('registrations', 1);
        $this->assertDatabaseHas('registrations', ['evento_id' => $evento->id, 'pago_status' => 'pending']);
    }

    public function test_store_valida_campos_basicos_antes_de_tocar_la_bd(): void
    {
        [$evento, $formType, $categoria] = $this->crearEventoConFormaDePago();

        $this->postJson('/registro', ['form_type_id' => $formType->id, 'tipoPago' => 'pendiente', 'auth_token' => 'x', 'participantes' => [$this->participantePayload($categoria)]])
            ->assertStatus(400)->assertJsonPath('error', 'Falta el ID del evento.');

        $this->postJson('/registro', ['evento_id' => $evento->id, 'form_type_id' => $formType->id, 'tipoPago' => 'pendiente', 'auth_token' => 'token-invalido', 'participantes' => [$this->participantePayload($categoria)]])
            ->assertStatus(401);

        $this->assertDatabaseCount('registrations', 0);
    }

    public function test_store_evento_inexistente_da_404(): void
    {
        [, $token] = $this->actingComoPersona();

        $this->postJson('/registro', [
            'evento_id' => 999999, 'form_type_id' => 1, 'tipoPago' => 'pendiente',
            'auth_token' => $token, 'participantes' => [['categoria' => 1]],
        ])->assertStatus(404);
    }

    public function test_store_categoria_con_precio_no_coincidente_es_rechazada(): void
    {
        [$evento, $formType, $categoria] = $this->crearEventoConFormaDePago();
        [, $token] = $this->actingComoPersona();

        $this->postJson('/registro', [
            'evento_id' => $evento->id, 'form_type_id' => $formType->id, 'tipoPago' => 'pendiente',
            'auth_token' => $token,
            'participantes' => [$this->participantePayload($categoria, ['precioCategoria' => 999])],
        ])->assertStatus(422)->assertJsonPath('error', "Categoría '{$categoria->id}' no válida para este evento.");
    }

    // ── update() ─────────────────────────────────────────────

    public function test_update_delega_a_actualizarinscripcionaction(): void
    {
        [$evento, $formType, $categoria] = $this->crearEventoConFormaDePago();
        [, $token] = $this->actingComoPersona();

        $store = $this->postJson('/registro', [
            'evento_id' => $evento->id, 'form_type_id' => $formType->id, 'tipoPago' => 'pendiente',
            'auth_token' => $token, 'participantes' => [$this->participantePayload($categoria)],
        ])->assertOk();
        $referencia = $store->json('referencia');

        $this->putJson('/registro/'.$referencia, [
            'evento_id' => $evento->id, 'form_type_id' => $formType->id,
            'participantes' => [$this->participantePayload($categoria, ['nombre' => 'Ana Actualizada'])],
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('participantes', ['nombre' => 'Ana Actualizada']);
    }

    public function test_update_referencia_inexistente_da_404(): void
    {
        [$evento, $formType, $categoria] = $this->crearEventoConFormaDePago();

        $this->putJson('/registro/LA-NOEXISTE', [
            'evento_id' => $evento->id, 'form_type_id' => $formType->id,
            'participantes' => [$this->participantePayload($categoria)],
        ])->assertStatus(404)->assertJsonPath('error', 'Inscripción no encontrada.');
    }

    // ── marcarPagada() ───────────────────────────────────────

    public function test_marcar_pagada_exige_confirmacion(): void
    {
        [$evento, $formType, $categoria] = $this->crearEventoConFormaDePago();
        [, $token] = $this->actingComoPersona();

        $store = $this->postJson('/registro', [
            'evento_id' => $evento->id, 'form_type_id' => $formType->id, 'tipoPago' => 'pendiente',
            'auth_token' => $token, 'participantes' => [$this->participantePayload($categoria)],
        ])->assertOk();
        $referencia = $store->json('referencia');
        Registration::where('referencia', $referencia)->update(['pago_status' => 'paid']);

        $this->patchJson('/registro/'.$referencia.'/pagada', [
            'evento_id' => $evento->id, 'form_type_id' => $formType->id,
            'participantes' => [$this->participantePayload($categoria)],
            'confirmacion' => false,
        ])->assertStatus(422);
    }

    public function test_marcar_pagada_delega_a_actualizarinscripcionpagadaaction(): void
    {
        [$evento, $formType, $categoria] = $this->crearEventoConFormaDePago();
        [, $token] = $this->actingComoPersona();

        $store = $this->postJson('/registro', [
            'evento_id' => $evento->id, 'form_type_id' => $formType->id, 'tipoPago' => 'pendiente',
            'auth_token' => $token, 'participantes' => [$this->participantePayload($categoria)],
        ])->assertOk();
        $referencia = $store->json('referencia');
        Registration::where('referencia', $referencia)->update(['pago_status' => 'paid']);

        $this->patchJson('/registro/'.$referencia.'/pagada', [
            'evento_id' => $evento->id, 'form_type_id' => $formType->id,
            'participantes' => [$this->participantePayload($categoria, ['souvenirs' => []])],
            'confirmacion' => true,
        ])->assertOk()->assertJsonPath('success', true)->assertJsonStructure(['costo_adicion']);
    }

    // ── PagoProxyController::estado() ───────────────────────

    public function test_estado_referencia_inexistente_da_404(): void
    {
        $this->getJson('/pago/LA-NOEXISTE/estado')->assertStatus(404);
    }

    public function test_estado_ya_pagada_devuelve_paid_de_inmediato(): void
    {
        [$evento, $formType, $categoria] = $this->crearEventoConFormaDePago();
        [, $token] = $this->actingComoPersona();
        $store = $this->postJson('/registro', [
            'evento_id' => $evento->id, 'form_type_id' => $formType->id, 'tipoPago' => 'pendiente',
            'auth_token' => $token, 'participantes' => [$this->participantePayload($categoria)],
        ])->assertOk();
        $referencia = $store->json('referencia');
        Registration::where('referencia', $referencia)->update(['pago_status' => 'paid']);

        $this->getJson('/pago/'.$referencia.'/estado')
            ->assertOk()->assertJsonPath('status', 'paid');
    }

    public function test_estado_pendiente_reciente_cae_al_fallback_simulado_con_remaining(): void
    {
        [$evento, $formType, $categoria] = $this->crearEventoConFormaDePago();
        [, $token] = $this->actingComoPersona();
        $store = $this->postJson('/registro', [
            'evento_id' => $evento->id, 'form_type_id' => $formType->id, 'tipoPago' => 'pendiente',
            'auth_token' => $token, 'participantes' => [$this->participantePayload($categoria)],
        ])->assertOk();
        $referencia = $store->json('referencia');

        $this->getJson('/pago/'.$referencia.'/estado')
            ->assertOk()->assertJsonPath('status', 'pending')->assertJsonStructure(['remaining']);
    }

    public function test_estado_marca_pagado_automaticamente_pasados_los_90_segundos(): void
    {
        [$evento, $formType, $categoria] = $this->crearEventoConFormaDePago();
        [, $token] = $this->actingComoPersona();
        $store = $this->postJson('/registro', [
            'evento_id' => $evento->id, 'form_type_id' => $formType->id, 'tipoPago' => 'pendiente',
            'auth_token' => $token, 'participantes' => [$this->participantePayload($categoria)],
        ])->assertOk();
        $referencia = $store->json('referencia');
        Registration::where('referencia', $referencia)->update(['fecha' => now()->subSeconds(100)]);

        $this->getJson('/pago/'.$referencia.'/estado')
            ->assertOk()->assertJsonPath('status', 'paid');

        $this->assertDatabaseHas('registrations', ['referencia' => $referencia, 'pago_status' => 'paid']);
    }

    // ── Webhooks ──────────────────────────────────────────────

    public function test_webhook_sip_sin_integracion_configurada_da_503(): void
    {
        $this->mock(QrProviderService::class, fn ($mock) => $mock->shouldReceive('sipClient')->andReturn(null));

        $this->postJson('/webhooks/sip/callback', ['alias' => 'LA-ABC'])
            ->assertStatus(503)->assertJsonPath('codigo', '9999');
    }

    public function test_webhook_sip_sin_autenticacion_da_401(): void
    {
        $this->mock(QrProviderService::class, fn ($mock) => $mock->shouldReceive('sipClient')->andReturn([
            'client' => null,
            'config' => (object) [
                'storagePath' => sys_get_temp_dir().'/monolito-sip-test',
                'callbackBasicUser' => 'sip-user',
                'callbackBasicPassword' => 'sip-pass',
                'callbackToken' => 'token-secreto',
            ],
        ]));

        $this->postJson('/webhooks/sip/callback', ['alias' => 'LA-ABC'])
            ->assertStatus(401)->assertJsonPath('codigo', '9999');
    }

    public function test_webhook_sip_autenticado_marca_pagado_in_process(): void
    {
        [$evento, $formType, $categoria] = $this->crearEventoConFormaDePago();
        [, $token] = $this->actingComoPersona();
        $store = $this->postJson('/registro', [
            'evento_id' => $evento->id, 'form_type_id' => $formType->id, 'tipoPago' => 'pendiente',
            'auth_token' => $token, 'participantes' => [$this->participantePayload($categoria)],
        ])->assertOk();
        $referencia = $store->json('referencia');

        $this->mock(QrProviderService::class, fn ($mock) => $mock->shouldReceive('sipClient')->andReturn([
            'client' => null,
            'config' => (object) [
                'storagePath' => sys_get_temp_dir().'/monolito-sip-test',
                'callbackBasicUser' => 'sip-user',
                'callbackBasicPassword' => 'sip-pass',
                'callbackToken' => '',
            ],
        ]));

        $this->withHeaders(['Authorization' => 'Basic '.base64_encode('sip-user:sip-pass')])
            ->postJson('/webhooks/sip/callback', ['alias' => $referencia])
            ->assertOk()->assertJsonPath('codigo', '0000');

        $this->assertDatabaseHas('registrations', ['referencia' => $referencia, 'pago_status' => 'paid']);
    }

    public function test_webhook_multipago_sin_integracion_configurada_da_503(): void
    {
        $this->mock(QrProviderService::class, fn ($mock) => $mock->shouldReceive('multipagoClient')->andReturn(null));

        $this->postJson('/webhooks/multipago/callback', ['pay_order_number' => '123'])
            ->assertStatus(503)->assertJsonPath('ok', false);
    }
}

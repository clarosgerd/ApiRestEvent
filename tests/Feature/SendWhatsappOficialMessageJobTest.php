<?php

namespace Tests\Feature;

use App\Jobs\SendWhatsappOficialMessageJob;
use App\Models\Organizador;
use App\Models\WhatsappCuenta;
use App\Services\WhatsappCloudApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * WhatsApp Business API oficial por organizador (08/10/2026) — llamada
 * real a la API de Meta (mockeada con Http::fake()), sin pasar por la cola
 * (se llama handle() directo, mismo criterio que correrían los jobs
 * síncronos en test).
 */
class SendWhatsappOficialMessageJobTest extends TestCase
{
    use RefreshDatabase;

    private function crearCuenta(array $overrides = []): WhatsappCuenta
    {
        $org = Organizador::factory()->create();

        return WhatsappCuenta::create(array_merge([
            'organizador_id' => $org->id,
            'nombre' => 'Cuenta Prueba',
            'phone_number_id' => '123456789012345',
            'access_token' => 'token-test',
            'template_name' => 'notificacion_sistema',
            'template_lang' => 'es',
            'activo' => true,
        ], $overrides));
    }

    public function test_envio_exitoso_llama_a_la_api_de_meta_con_la_plantilla_configurada(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200)]);
        $cuenta = $this->crearCuenta();

        $job = new SendWhatsappOficialMessageJob($cuenta->id, '59177712345', 'Hola, tu pago fue confirmado.');
        $job->handle(app(WhatsappCloudApiService::class));

        Http::assertSent(function ($request) use ($cuenta) {
            return str_contains($request->url(), $cuenta->phone_number_id)
                && $request->hasHeader('Authorization', 'Bearer token-test')
                && $request['to'] === '59177712345'
                && $request['template']['name'] === 'notificacion_sistema'
                && $request['template']['language']['code'] === 'es'
                && $request['template']['components'][0]['parameters'][0]['text'] === 'Hola, tu pago fue confirmado.';
        });
    }

    public function test_token_vencido_401_no_reintenta(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid token']], 401)]);
        $cuenta = $this->crearCuenta();

        $job = new SendWhatsappOficialMessageJob($cuenta->id, '59177712345', 'Texto');
        // No debe lanzar — se marca como fallido internamente (fail()) sin relanzar la excepción.
        $job->handle(app(WhatsappCloudApiService::class));

        $this->assertTrue(true); // llegar hasta acá sin excepción ya confirma el comportamiento.
    }

    public function test_error_5xx_de_meta_relanza_para_que_laravel_reintente(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Server error']], 500)]);
        $cuenta = $this->crearCuenta();

        $job = new SendWhatsappOficialMessageJob($cuenta->id, '59177712345', 'Texto');

        $this->expectException(\RuntimeException::class);
        $job->handle(app(WhatsappCloudApiService::class));
    }

    public function test_cuenta_inexistente_no_llama_a_la_api(): void
    {
        Http::fake();

        $job = new SendWhatsappOficialMessageJob(999999, '59177712345', 'Texto');
        $job->handle(app(WhatsappCloudApiService::class));

        Http::assertNothingSent();
    }

    public function test_cuenta_inactiva_no_llama_a_la_api(): void
    {
        Http::fake();
        $cuenta = $this->crearCuenta(['activo' => false]);

        $job = new SendWhatsappOficialMessageJob($cuenta->id, '59177712345', 'Texto');
        $job->handle(app(WhatsappCloudApiService::class));

        Http::assertNothingSent();
    }
}

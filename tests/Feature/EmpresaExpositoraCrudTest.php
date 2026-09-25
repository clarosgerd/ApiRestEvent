<?php

namespace Tests\Feature;

use App\Mail\ExpositorCredencialesMail;
use App\Models\AdminUser;
use App\Models\EmpresaExpositora;
use App\Models\FormType;
use App\Models\LeadCapturado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\ArmaExpositores;
use Tests\TestCase;

/**
 * SmartStand (25/09/2026) — gestión de expositores por el organizador
 * (guard `admins`, scoping por evento) + flag `es_expositor` y
 * `expositoresConfig` del evento.
 */
class EmpresaExpositoraCrudTest extends TestCase
{
    use RefreshDatabase, ArmaExpositores;

    private function como(AdminUser $admin): void
    {
        $this->app['auth']->forgetGuards();
        $this->actingAsAdmin($admin);
    }

    public function test_index_ordena_por_leads_capturados_que_es_el_ranking(): void
    {
        $evento = $this->crearEvento();
        $pocos = $this->crearCuenta($evento, ['nombre' => 'Pocos']);
        $muchos = $this->crearCuenta($evento, ['nombre' => 'Muchos']);
        $ninguno = $this->crearCuenta($evento, ['nombre' => 'Ninguno']);
        $a = $this->crearAsistente($evento);
        $b = $this->crearAsistente($evento);
        LeadCapturado::create(['empresa_expositora_id' => $pocos->id, 'participante_id' => $a->id, 'capturado_at' => now()]);
        LeadCapturado::create(['empresa_expositora_id' => $muchos->id, 'participante_id' => $a->id, 'capturado_at' => now()]);
        LeadCapturado::create(['empresa_expositora_id' => $muchos->id, 'participante_id' => $b->id, 'capturado_at' => now()]);
        $this->actingAsAdmin();

        $response = $this->getJson("/api/v1/event/{$evento->id}/empresas-expositoras");

        $response->assertOk();
        $this->assertSame(['Muchos', 'Pocos', 'Ninguno'], collect($response->json('expositores'))->pluck('nombre')->all());
        $this->assertSame([2, 1, 0], collect($response->json('expositores'))->pluck('leadsCount')->all());
        $this->assertArrayNotHasKey('password', $response->json('expositores.0'));
    }

    public function test_un_admin_de_otro_evento_recibe_403_y_sin_login_401(): void
    {
        $evento = $this->crearEvento();
        $otroEvento = $this->crearEvento();
        $cuenta = $this->crearCuenta($evento);

        $this->getJson("/api/v1/event/{$evento->id}/empresas-expositoras")->assertStatus(401);

        $this->como(AdminUser::factory()->scopedTo($otroEvento->id)->create());
        $this->getJson("/api/v1/event/{$evento->id}/empresas-expositoras")->assertStatus(403);
        $this->putJson("/api/v1/empresas-expositoras/{$cuenta->id}", ['nombre' => 'X'])->assertStatus(403);
        $this->deleteJson("/api/v1/empresas-expositoras/{$cuenta->id}")->assertStatus(403);
        $this->postJson("/api/v1/empresas-expositoras/{$cuenta->id}/reenviar-credenciales")->assertStatus(403);
        $this->getJson("/api/v1/empresas-expositoras/{$cuenta->id}/dashboard")->assertStatus(403);

        $this->como(AdminUser::factory()->scopedTo($evento->id)->create());
        $this->getJson("/api/v1/event/{$evento->id}/empresas-expositoras")->assertOk();
    }

    public function test_store_crea_la_cuenta_y_manda_las_credenciales_sin_devolver_la_contrasena(): void
    {
        Mail::fake();
        $evento = $this->crearEvento();
        $categoria = $this->crearCategoria($evento, null, 'Isla');
        $this->actingAsAdmin();

        $response = $this->postJson("/api/v1/event/{$evento->id}/empresas-expositoras", [
            'nombre' => 'Farma Andina', 'email' => 'Contacto@Farma.test', 'stand' => 'A-01', 'categoria_id' => $categoria->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('credencialesEnviadas', true)
            ->assertJsonPath('expositor.email', 'contacto@farma.test')
            ->assertJsonPath('expositor.tamanoStand', 'Isla');
        $this->assertStringNotContainsString('password', $response->getContent());
        Mail::assertSent(ExpositorCredencialesMail::class, fn ($m) => $m->hasTo('contacto@farma.test'));
        $this->assertNotNull(EmpresaExpositora::firstOrFail()->credenciales_enviadas_at);
    }

    public function test_store_rechaza_correo_repetido_en_el_evento_y_categoria_de_otro_evento(): void
    {
        Mail::fake();
        $evento = $this->crearEvento();
        $otroEvento = $this->crearEvento();
        $categoriaAjena = $this->crearCategoria($otroEvento);
        $this->crearCuenta($evento, ['email' => 'dup@farma.test']);
        $this->actingAsAdmin();

        $this->postJson("/api/v1/event/{$evento->id}/empresas-expositoras", ['nombre' => 'X', 'email' => 'dup@farma.test'])
            ->assertStatus(422)->assertJsonValidationErrors('email');
        $this->postJson("/api/v1/event/{$evento->id}/empresas-expositoras", ['nombre' => 'X', 'email' => 'nuevo@farma.test', 'categoria_id' => $categoriaAjena->id])
            ->assertStatus(422)->assertJsonValidationErrors('categoria_id');

        // El mismo correo SÍ puede existir en otro evento.
        $this->postJson("/api/v1/event/{$otroEvento->id}/empresas-expositoras", ['nombre' => 'X', 'email' => 'dup@farma.test'])
            ->assertCreated();
    }

    public function test_update_edita_y_al_desactivar_revoca_los_tokens(): void
    {
        $evento = $this->crearEvento();
        $cuenta = $this->crearCuenta($evento, ['nombre' => 'Vieja']);
        $cuenta->createToken('app-celular-1');
        $cuenta->createToken('app-celular-2');
        $this->actingAsAdmin();

        $this->putJson("/api/v1/empresas-expositoras/{$cuenta->id}", ['nombre' => 'Nueva', 'stand' => 'C-07'])
            ->assertOk()->assertJsonPath('expositor.nombre', 'Nueva')->assertJsonPath('expositor.stand', 'C-07');
        $this->assertSame(2, $cuenta->tokens()->count());

        $this->putJson("/api/v1/empresas-expositoras/{$cuenta->id}", ['activo' => false])->assertOk();
        $this->assertSame(0, $cuenta->tokens()->count(), 'Desactivar cierra las sesiones de la app.');
        $this->assertFalse($cuenta->fresh()->activo);
    }

    public function test_update_no_permite_pisar_el_correo_de_otra_empresa_del_evento(): void
    {
        $evento = $this->crearEvento();
        $this->crearCuenta($evento, ['email' => 'a@farma.test']);
        $b = $this->crearCuenta($evento, ['email' => 'b@farma.test']);
        $this->actingAsAdmin();

        $this->putJson("/api/v1/empresas-expositoras/{$b->id}", ['email' => 'a@farma.test'])
            ->assertStatus(422)->assertJsonValidationErrors('email');
        $this->putJson("/api/v1/empresas-expositoras/{$b->id}", ['email' => 'b@farma.test'])->assertOk();
    }

    public function test_destroy_bloquea_si_ya_capturo_contactos(): void
    {
        $evento = $this->crearEvento();
        $conLeads = $this->crearCuenta($evento);
        $sinLeads = $this->crearCuenta($evento);
        $a = $this->crearAsistente($evento);
        LeadCapturado::create(['empresa_expositora_id' => $conLeads->id, 'participante_id' => $a->id, 'capturado_at' => now()]);
        $this->actingAsAdmin();

        $this->deleteJson("/api/v1/empresas-expositoras/{$conLeads->id}")->assertStatus(409);
        $this->assertNotNull(EmpresaExpositora::find($conLeads->id));

        $this->deleteJson("/api/v1/empresas-expositoras/{$sinLeads->id}")->assertOk();
        $this->assertNull(EmpresaExpositora::find($sinLeads->id));
    }

    public function test_reenviar_credenciales_regenera_la_contrasena_y_manda_el_correo(): void
    {
        Mail::fake();
        $evento = $this->crearEvento();
        $cuenta = $this->crearCuenta($evento, ['email' => 'a@farma.test'], 'vieja-clave');
        $this->actingAsAdmin();

        $this->postJson("/api/v1/empresas-expositoras/{$cuenta->id}/reenviar-credenciales")->assertOk();

        $cuenta->refresh();
        $this->assertFalse(\Illuminate\Support\Facades\Hash::check('vieja-clave', $cuenta->password));
        Mail::assertSent(ExpositorCredencialesMail::class, fn ($m) => \Illuminate\Support\Facades\Hash::check($m->passwordPlano, $cuenta->password));
    }

    public function test_dashboard_del_organizador_devuelve_los_datos_de_la_empresa(): void
    {
        $evento = $this->crearEvento();
        $cuenta = $this->crearCuenta($evento, ['nombre' => 'Farma Sur']);
        $a = $this->crearAsistente($evento, ['ciudad' => 'Tarija']);
        LeadCapturado::create(['empresa_expositora_id' => $cuenta->id, 'participante_id' => $a->id, 'capturado_at' => now(), 'calificacion' => 2]);
        $this->actingAsAdmin();

        $this->getJson("/api/v1/empresas-expositoras/{$cuenta->id}/dashboard")
            ->assertOk()
            ->assertJsonPath('expositor.nombre', 'Farma Sur')
            ->assertJsonPath('data.totalLeads', 1)
            ->assertJsonPath('data.porCiudad.0.ciudad', 'Tarija')
            ->assertJsonPath('data.calificacionPromedio', 2);
    }

    // ── Flag es_expositor y expositoresConfig ───────────────────────────

    public function test_form_type_acepta_y_expone_es_expositor(): void
    {
        $this->actingAsAdmin();
        $evento = $this->crearEvento();

        $this->postJson('/api/v1/form-type', [
            'event_id' => $evento->id, 'name' => 'Expositor', 'icon' => '🏢', 'description' => 'x',
            'cupo_total' => 50, 'precio_base' => 0, 'costo_edicion' => 0, 'tiempo_expiracion_min' => 30,
            'color' => '#00bad2', 'es_expositor' => true,
        ])->assertCreated()->assertJsonPath('formType.esExpositor', true);

        $sinFlag = FormType::factory()->create(['event_id' => $evento->id]);
        $this->assertFalse((bool) $sinFlag->fresh()->es_expositor, 'Default false: no cambia los form_types existentes.');

        $this->putJson('/api/v1/form-type/' . $sinFlag->id, ['es_expositor' => true])
            ->assertOk()->assertJsonPath('formType.esExpositor', true);
    }

    public function test_evento_persiste_y_expone_expositores_config(): void
    {
        $this->actingAsAdmin();
        $evento = $this->crearEvento();
        $config = [
            'app_url_ios' => 'https://apps.apple.com/app/smartstand',
            'app_url_android' => 'https://play.google.com/store/apps/details?id=smartstand',
            'instrucciones' => 'Retira tu credencial.',
        ];

        $this->putJson("/api/v1/event/{$evento->id}", ['expositoresConfig' => $config])->assertOk();

        $this->assertSame($config['app_url_ios'], $evento->fresh()->expositores_config['app_url_ios']);
        $this->getJson("/api/v1/event/{$evento->id}")->assertJsonPath('eventos.expositoresConfig.app_url_android', $config['app_url_android']);
    }

    public function test_expositores_config_valida_que_los_links_sean_urls(): void
    {
        $this->actingAsAdmin();
        $evento = $this->crearEvento();

        $this->putJson("/api/v1/event/{$evento->id}", ['expositoresConfig' => ['app_url_ios' => 'no-es-una-url']])
            ->assertStatus(422)->assertJsonValidationErrors('expositoresConfig.app_url_ios');
    }
}

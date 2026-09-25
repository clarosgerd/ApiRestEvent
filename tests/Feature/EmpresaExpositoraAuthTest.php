<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Club;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\ArmaExpositores;
use Tests\TestCase;

/**
 * SmartStand (25/09/2026) — login/logout/me de la empresa expositora
 * (guard `expositores`, molde Club).
 */
class EmpresaExpositoraAuthTest extends TestCase
{
    use RefreshDatabase, ArmaExpositores;

    public function test_login_correcto_devuelve_token_y_datos_de_la_empresa(): void
    {
        $evento = $this->crearEvento();
        $categoria = $this->crearCategoria($evento, null, 'Stand 6x3');
        $cuenta = $this->crearCuenta($evento, ['email' => 'a@empresa.test', 'categoria_id' => $categoria->id, 'stand' => 'B-12'], 'secreta-123');

        $response = $this->postJson('/api/v1/expositor/login', ['email' => 'a@empresa.test', 'password' => 'secreta-123']);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.empresa.id', $cuenta->id)
            ->assertJsonPath('data.empresa.tamanoStand', 'Stand 6x3')
            ->assertJsonPath('data.empresa.stand', 'B-12');
        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_login_con_contrasena_incorrecta_da_401(): void
    {
        $evento = $this->crearEvento();
        $this->crearCuenta($evento, ['email' => 'a@empresa.test'], 'secreta-123');

        $this->postJson('/api/v1/expositor/login', ['email' => 'a@empresa.test', 'password' => 'otra'])
            ->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_login_es_insensible_a_mayusculas_en_el_correo(): void
    {
        $evento = $this->crearEvento();
        $this->crearCuenta($evento, ['email' => 'a@empresa.test'], 'secreta-123');

        $this->postJson('/api/v1/expositor/login', ['email' => 'A@Empresa.TEST', 'password' => 'secreta-123'])
            ->assertOk();
    }

    public function test_cuenta_desactivada_no_puede_loguearse(): void
    {
        $evento = $this->crearEvento();
        $this->crearCuenta($evento, ['email' => 'a@empresa.test', 'activo' => false], 'secreta-123');

        $this->postJson('/api/v1/expositor/login', ['email' => 'a@empresa.test', 'password' => 'secreta-123'])
            ->assertStatus(403);
    }

    /** El mismo correo puede tener una cuenta por evento: gana la cuya contraseña coincide. */
    public function test_mismo_correo_en_dos_eventos_resuelve_por_contrasena(): void
    {
        $evento1 = $this->crearEvento();
        $evento2 = $this->crearEvento();
        $c1 = $this->crearCuenta($evento1, ['email' => 'a@empresa.test'], 'clave-evento-1');
        $c2 = $this->crearCuenta($evento2, ['email' => 'a@empresa.test'], 'clave-evento-2');

        $this->postJson('/api/v1/expositor/login', ['email' => 'a@empresa.test', 'password' => 'clave-evento-2'])
            ->assertOk()
            ->assertJsonPath('data.empresa.id', $c2->id);
        $this->postJson('/api/v1/expositor/login', ['email' => 'a@empresa.test', 'password' => 'clave-evento-1'])
            ->assertOk()
            ->assertJsonPath('data.empresa.id', $c1->id);
    }

    public function test_me_y_logout(): void
    {
        $evento = $this->crearEvento();
        $cuenta = $this->crearCuenta($evento);
        $this->comoExpositor($cuenta);

        $this->getJson('/api/v1/expositor/me')->assertOk()->assertJsonPath('data.id', $cuenta->id);

        $this->postJson('/api/v1/expositor/logout')->assertOk();
        $this->assertSame(0, $cuenta->tokens()->count());
    }

    public function test_sin_token_da_401(): void
    {
        $this->getJson('/api/v1/expositor/me')->assertStatus(401);
        $this->getJson('/api/v1/expositor/leads')->assertStatus(401);
    }

    /**
     * Aislamiento entre guards: con `auth:sanctum` genérico un token de OTRO
     * tipo de actor pasaría el middleware y `$request->user()` sería otro
     * modelo. Con `auth:expositores` Sanctum lo rechaza.
     */
    public function test_token_de_admin_o_de_club_no_sirve_en_endpoints_de_expositor(): void
    {
        $admin = AdminUser::factory()->create();
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer ' . $admin->createToken('t')->plainTextToken);
        $this->getJson('/api/v1/expositor/me')->assertStatus(401);
        $this->getJson('/api/v1/expositor/leads')->assertStatus(401);

        $club = Club::create(['nombre' => 'Club X', 'email' => 'club@x.test', 'password' => Hash::make('x'), 'activo' => true]);
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer ' . $club->createToken('t')->plainTextToken);
        $this->getJson('/api/v1/expositor/me')->assertStatus(401);
    }
}

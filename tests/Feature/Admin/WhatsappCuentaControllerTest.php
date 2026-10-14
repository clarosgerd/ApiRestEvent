<?php

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use App\Models\Organizador;
use App\Models\WhatsappCuenta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * WhatsApp Business API oficial por organizador (08/10/2026, sincronizado al
 * monolito) — capa Admin\*, equivalente in-process al
 * WhatsappCuentaControllerTest de admin-eventos (que mockea Http::fake
 * porque ahí WhatsappCuentaController es un proxy HTTP; acá
 * Admin\WhatsappCuentaController llama directo al WhatsappCuentaController
 * de la API, así que se verifica el efecto real en BD).
 */
class WhatsappCuentaControllerTest extends TestCase
{
    use RefreshDatabase;

    private array $superSession;

    protected function setUp(): void
    {
        parent::setUp();

        $super = AdminUser::create([
            'nombre' => 'Super', 'email' => 'super@example.net',
            'password' => Hash::make('secret123'), 'rol' => 'super_admin',
            'activo' => true, 'evento_id' => null,
        ]);
        $this->superSession = [
            'admin_token' => $super->createToken('t')->plainTextToken,
            'admin_user' => ['id' => $super->id, 'rol' => 'super_admin'],
        ];
    }

    public function test_index_lista_cuentas(): void
    {
        $organizador = Organizador::factory()->create();
        WhatsappCuenta::create([
            'organizador_id' => $organizador->id,
            'nombre' => 'WhatsApp Test',
            'phone_number_id' => '123456',
            'access_token' => 'secret-token',
            'activo' => true,
        ]);

        $response = $this->withSession($this->superSession)->get('/admin/whatsapp-cuentas');

        $response->assertOk();
        $response->assertSee('WhatsApp Test');
        $response->assertDontSee('secret-token');
    }

    public function test_store_crea_la_cuenta(): void
    {
        $organizador = Organizador::factory()->create();

        $response = $this->withSession($this->superSession)->post('/admin/whatsapp-cuentas', [
            'organizador_id' => $organizador->id,
            'nombre' => 'WhatsApp COLABIOCLI',
            'phone_number_id' => '999888777',
            'access_token' => 'token-real',
            'template_name' => 'notificacion_sistema',
            'template_lang' => 'es',
            'activo' => '1',
        ]);

        $response->assertRedirect(route('admin.whatsapp-cuentas.index'));
        $this->assertDatabaseHas('whatsapp_cuentas', [
            'organizador_id' => $organizador->id,
            'nombre' => 'WhatsApp COLABIOCLI',
            'phone_number_id' => '999888777',
        ]);
    }

    /**
     * "Dejar vacío para no cambiar" (08/10/2026) — un access_token ausente
     * en el form de edición no debe pisar el valor real ya guardado.
     */
    public function test_update_sin_access_token_no_lo_pisa(): void
    {
        $cuenta = WhatsappCuenta::create([
            'nombre' => 'WhatsApp Test', 'phone_number_id' => '111',
            'access_token' => 'token-original', 'activo' => true,
        ]);

        $response = $this->withSession($this->superSession)->put("/admin/whatsapp-cuentas/{$cuenta->id}", [
            'nombre' => 'WhatsApp Test Editado',
            'phone_number_id' => '111',
            'access_token' => '',
        ]);

        $response->assertRedirect(route('admin.whatsapp-cuentas.index'));
        $this->assertSame('token-original', $cuenta->refresh()->access_token);
        $this->assertSame('WhatsApp Test Editado', $cuenta->nombre);
    }

    public function test_destroy_elimina_la_cuenta(): void
    {
        $cuenta = WhatsappCuenta::create([
            'nombre' => 'WhatsApp Test', 'phone_number_id' => '111',
            'access_token' => 'token', 'activo' => true,
        ]);

        $this->withSession($this->superSession)->delete("/admin/whatsapp-cuentas/{$cuenta->id}");

        $this->assertDatabaseMissing('whatsapp_cuentas', ['id' => $cuenta->id]);
    }

    /**
     * El <select> de whatsapp_canal en organizadores/index.blade.php,
     * opt-in estricto: solo un super_admin lo cambia, nunca se activa solo.
     */
    public function test_organizadores_index_muestra_el_select_de_whatsapp_canal(): void
    {
        Organizador::factory()->create(['whatsapp_canal' => 'oficial']);

        $html = $this->withSession($this->superSession)->get('/admin/organizadores')->assertOk()->getContent();

        $this->assertStringContainsString('name="whatsapp_canal"', $html);
        $this->assertMatchesRegularExpression('/<option value="oficial"[^>]*selected/', $html);
    }

    public function test_organizadores_update_persiste_whatsapp_canal(): void
    {
        $organizador = Organizador::factory()->create(['whatsapp_canal' => 'ninguno']);

        $this->withSession($this->superSession)->put("/admin/organizadores/{$organizador->id}", [
            'razon_social' => $organizador->razon_social,
            'email' => $organizador->email,
            'whatsapp_canal' => 'oficial',
        ]);

        $this->assertSame('oficial', $organizador->refresh()->whatsapp_canal);
    }
}

<?php

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Consolidación monolito (21/08/2026), Fase 1a — smoke test end-to-end del
 * panel admin fusionado (login por sesión + guard `admins` alimentado vía
 * InjectAdminSessionToken + delegación a los controllers de la API). Ver
 * ApiRestEvent/brain/api_rest_event/PLAN-CONSOLIDACION-MONOLITO-21082026.md.
 */
class CatalogosPaisesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_es_redirigido_al_login_del_panel(): void
    {
        $this->get('/admin/catalogos')->assertRedirect(route('admin.login'));
    }

    // Renombrado 21/08/2026, Fase 1e-i: admin.dashboard ya existe, así que
    // el login aterriza ahí (AuthController ya usaba Route::has('admin.dashboard')
    // como fallback desde antes — no cambió código, solo dejó de caer al
    // fallback ahora que la ruta real existe).
    public function test_login_exitoso_deja_sesion_con_token_y_llega_al_dashboard(): void
    {
        AdminUser::create([
            'nombre' => 'Super', 'email' => 'super@example.net',
            'password' => Hash::make('secret123'), 'rol' => 'super_admin',
            'activo' => true, 'evento_id' => null,
        ]);

        $response = $this->post('/admin/login', ['email' => 'super@example.net', 'password' => 'secret123']);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertNotNull(session('admin_token'));
        $this->assertSame('super_admin', session('admin_user')['rol']);
    }

    public function test_super_admin_autenticado_ve_el_listado_de_paises(): void
    {
        $admin = AdminUser::create([
            'nombre' => 'Super', 'email' => 'super2@example.net',
            'password' => Hash::make('secret123'), 'rol' => 'super_admin',
            'activo' => true, 'evento_id' => null,
        ]);
        $token = $admin->createToken('auth-token')->plainTextToken;

        $response = $this->withSession(['admin_token' => $token, 'admin_user' => ['rol' => 'super_admin']])
            ->get('/admin/catalogos/paises');

        $response->assertOk();
        $response->assertViewIs('admin.catalogos.paises');
    }

    public function test_admin_no_super_admin_recibe_403_en_catalogos(): void
    {
        $admin = AdminUser::create([
            'nombre' => 'Admin normal', 'email' => 'admin@example.net',
            'password' => Hash::make('secret123'), 'rol' => 'admin',
            'activo' => true, 'evento_id' => null,
        ]);
        $token = $admin->createToken('auth-token')->plainTextToken;

        $response = $this->withSession(['admin_token' => $token, 'admin_user' => ['rol' => 'admin']])
            ->get('/admin/catalogos/paises');

        $response->assertForbidden();
    }

    public function test_crud_completo_de_pais_a_traves_del_panel_delega_en_el_controller_de_la_api(): void
    {
        $admin = AdminUser::create([
            'nombre' => 'Super', 'email' => 'super3@example.net',
            'password' => Hash::make('secret123'), 'rol' => 'super_admin',
            'activo' => true, 'evento_id' => null,
        ]);
        $token = $admin->createToken('auth-token')->plainTextToken;
        $session = ['admin_token' => $token, 'admin_user' => ['rol' => 'super_admin']];

        // Crear
        $create = $this->withSession($session)->post('/admin/catalogos/paises', [
            'nombre' => 'PaisTest', 'iso2' => 'PT', 'iso3' => 'PTX', 'activo' => 1,
        ]);
        $create->assertRedirect(route('admin.catalogos.paises.index'));
        $this->assertDatabaseHas('paises', ['nombre' => 'PaisTest', 'iso2' => 'PT']);
        $pais = \App\Models\Pais::where('nombre', 'PaisTest')->firstOrFail();

        // Editar
        $update = $this->withSession($session)->put("/admin/catalogos/paises/{$pais->id}", [
            'nombre' => 'PaisTestEditado', 'iso2' => 'PT', 'iso3' => 'PTX', 'activo' => 1,
        ]);
        $update->assertRedirect(route('admin.catalogos.paises.index'));
        $this->assertDatabaseHas('paises', ['id' => $pais->id, 'nombre' => 'PaisTestEditado']);

        // Auditoría real (AdminAuditLogger, el mismo que usa la API) —
        // confirma que la delegación no se salteó ese efecto secundario.
        $this->assertDatabaseHas('admin_audit_logs', ['entidad' => 'Pais', 'entidad_id' => $pais->id, 'accion' => 'create']);
        $this->assertDatabaseHas('admin_audit_logs', ['entidad' => 'Pais', 'entidad_id' => $pais->id, 'accion' => 'update']);

        // Borrar
        $destroy = $this->withSession($session)->delete("/admin/catalogos/paises/{$pais->id}");
        $destroy->assertRedirect(route('admin.catalogos.paises.index'));
        $this->assertDatabaseMissing('paises', ['id' => $pais->id]);
    }
}

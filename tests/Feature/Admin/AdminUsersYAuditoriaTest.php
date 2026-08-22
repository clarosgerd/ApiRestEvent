<?php

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use App\Models\Evento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Consolidación monolito (21/08/2026), Fase 1b — gestión de usuarios admin
 * + auditoría + restricción de cajero. Mismo patrón de delegación y
 * verificación que Fase 1a (CatalogosPaisesTest). Ver
 * ApiRestEvent/brain/api_rest_event/PLAN-CONSOLIDACION-MONOLITO-21082026.md.
 */
class AdminUsersYAuditoriaTest extends TestCase
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
        $token = $super->createToken('auth-token')->plainTextToken;
        $this->superSession = ['admin_token' => $token, 'admin_user' => ['id' => $super->id, 'rol' => 'super_admin']];
    }

    public function test_crud_completo_de_usuario_admin_a_traves_del_panel(): void
    {
        $evento = Evento::factory()->create();

        $this->withSession($this->superSession)->get('/admin/usuarios')
            ->assertOk()->assertViewIs('admin.usuarios.index');

        $this->withSession($this->superSession)->get('/admin/usuarios/create')
            ->assertOk()->assertViewIs('admin.usuarios.form');

        $create = $this->withSession($this->superSession)->post('/admin/usuarios', [
            'nombre' => 'Nuevo Admin', 'email' => 'nuevo-admin@example.net', 'password' => 'password123',
            'rol' => 'admin', 'evento_id' => $evento->id, 'activo' => 1,
        ]);
        $create->assertRedirect(route('admin.usuarios.index'));
        $nuevo = AdminUser::where('email', 'nuevo-admin@example.net')->firstOrFail();
        // La contraseña la hashea AdminUserController::store() de la API
        // (Hash::make dentro del controller delegado) — confirma que no se
        // guardó en texto plano.
        $this->assertTrue(Hash::check('password123', $nuevo->password));

        $this->withSession($this->superSession)->get("/admin/usuarios/{$nuevo->id}/edit")
            ->assertOk()->assertViewIs('admin.usuarios.form')
            ->assertSee('nuevo-admin@example.net');

        $update = $this->withSession($this->superSession)->put("/admin/usuarios/{$nuevo->id}", [
            'nombre' => 'Nuevo Admin Editado', 'email' => 'nuevo-admin@example.net',
            'rol' => 'admin', 'evento_id' => $evento->id, 'activo' => 1,
        ]);
        $update->assertRedirect(route('admin.usuarios.index'));
        $this->assertDatabaseHas('admin_users', ['id' => $nuevo->id, 'nombre' => 'Nuevo Admin Editado']);

        $destroy = $this->withSession($this->superSession)->delete("/admin/usuarios/{$nuevo->id}");
        $destroy->assertRedirect(route('admin.usuarios.index'));
        $this->assertDatabaseMissing('admin_users', ['id' => $nuevo->id]);
    }

    public function test_super_admin_no_puede_borrarse_a_si_mismo(): void
    {
        $super = AdminUser::where('email', 'super@example.net')->firstOrFail();

        $response = $this->withSession($this->superSession)->delete("/admin/usuarios/{$super->id}");

        // AdminUserController::destroy() de la API rechaza con 409/JSON —
        // el panel lo traduce a un redirect con error, no un crash.
        $response->assertRedirect();
        $response->assertSessionHasErrors();
        $this->assertDatabaseHas('admin_users', ['id' => $super->id]);
    }

    public function test_admin_no_super_admin_no_ve_usuarios_ni_auditoria(): void
    {
        $admin = AdminUser::create([
            'nombre' => 'Admin normal', 'email' => 'admin@example.net',
            'password' => Hash::make('secret123'), 'rol' => 'admin',
            'activo' => true, 'evento_id' => null,
        ]);
        $token = $admin->createToken('auth-token')->plainTextToken;
        $session = ['admin_token' => $token, 'admin_user' => ['rol' => 'admin']];

        $this->withSession($session)->get('/admin/usuarios')->assertForbidden();
        $this->withSession($session)->get('/admin/auditoria')->assertForbidden();
    }

    public function test_auditoria_lista_los_logs_reales_y_respeta_el_filtro_de_evento(): void
    {
        $eventoA = Evento::factory()->create();
        $eventoB = Evento::factory()->create();

        // Genera auditoría real haciendo una operación real por el panel
        // (crear un país en el evento A no aplica — Pais es global; para
        // generar auditoría scoped a evento_id, se usa la creación del
        // usuario admin de arriba mismo, que ya deja un log en
        // AdminUserController::store() — pero ese controller NO llama a
        // AdminAuditLogger. En su lugar, se inserta directo para probar
        // el filtro, que es lo que realmente hace AuditLogController.
        \App\Models\AdminAuditLog::create([
            'admin_user_id' => null, 'accion' => 'create', 'entidad' => 'Category',
            'entidad_id' => 1, 'evento_id' => $eventoA->id, 'datos_antes' => null, 'datos_despues' => [],
        ]);
        \App\Models\AdminAuditLog::create([
            'admin_user_id' => null, 'accion' => 'create', 'entidad' => 'Category',
            'entidad_id' => 2, 'evento_id' => $eventoB->id, 'datos_antes' => null, 'datos_despues' => [],
        ]);

        $sinFiltro = $this->withSession($this->superSession)->get('/admin/auditoria');
        $sinFiltro->assertOk();
        $this->assertCount(2, $sinFiltro->viewData('logs'));

        $conFiltro = $this->withSession($this->superSession)->get('/admin/auditoria?evento_id='.$eventoA->id);
        $conFiltro->assertOk();
        $logs = $conFiltro->viewData('logs');
        $this->assertCount(1, $logs);
        $this->assertSame($eventoA->id, $logs[0]['evento_id']);
    }

    public function test_cajero_es_restringido_fuera_de_caja(): void
    {
        $evento = Evento::factory()->create();
        $cajero = AdminUser::create([
            'nombre' => 'Cajero', 'email' => 'cajero@example.net',
            'password' => Hash::make('secret123'), 'rol' => 'cajero',
            'activo' => true, 'evento_id' => $evento->id,
        ]);
        $token = $cajero->createToken('auth-token')->plainTextToken;
        $session = ['admin_token' => $token, 'admin_user' => ['rol' => 'cajero', 'evento_id' => $evento->id]];

        // Actualizado 21/08/2026, Fase 1d: admin.caja.index ya existe, así
        // que ahora el middleware REDIRIGE a la caja del cajero en vez de
        // abortar con 403 (que era el comportamiento provisorio de la Fase
        // 1b, mientras Caja todavía no estaba migrada). Ver
        // CajeroFueraDeCajaSinRutaTest para el caso 403 (evento_id nulo).
        $response = $this->withSession($session)->get('/admin/catalogos');
        $response->assertRedirect(route('admin.caja.index', $evento->id));
    }

    /**
     * El otro branch del middleware (403 puro) — un cajero SIN evento_id
     * asignado no tiene a dónde redirigir, así que sigue abortando.
     */
    public function test_cajero_sin_evento_asignado_recibe_403(): void
    {
        $cajero = AdminUser::create([
            'nombre' => 'Cajero sin evento', 'email' => 'cajero-sin-evento@example.net',
            'password' => Hash::make('secret123'), 'rol' => 'cajero',
            'activo' => true, 'evento_id' => null,
        ]);
        $token = $cajero->createToken('auth-token')->plainTextToken;
        $session = ['admin_token' => $token, 'admin_user' => ['rol' => 'cajero', 'evento_id' => null]];

        $this->withSession($session)->get('/admin/catalogos')->assertForbidden();
    }
}

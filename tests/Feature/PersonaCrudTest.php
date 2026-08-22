<?php

namespace Tests\Feature;

use App\Models\Persona;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CRUD de personas (21/08/2026) — solo super_admin, mismo criterio que
 * OrganizadorCrudTest/SocioTest. Antes de esto, index()/show() eran
 * alcanzables por cualquier Persona autenticada (ver
 * test_persona_comun_no_puede_listar_ni_ver_otras_personas) y
 * store()/update()/destroy() eran stubs sin implementar.
 */
class PersonaCrudTest extends TestCase
{
    use RefreshDatabase;

    private function datosValidos(array $overrides = []): array
    {
        return array_merge([
            'nombre' => 'Ana',
            'apellido' => 'Prueba',
            'alias' => 'AnaP',
            'email' => 'ana.prueba@test.net',
            'password' => 'password123',
            'sexo' => 'Femenino',
            'tipo_documento' => 'DNI',
            'numero_documento' => '12345678',
            'fecha_nacimiento' => '1995-06-15',
            'direccion' => 'Calle Falsa 123',
            'ciudad' => 'La Paz',
            'telefono' => '77712345',
            'celular' => '77798765',
        ], $overrides);
    }

    public function test_super_admin_puede_listar_personas(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        Persona::factory()->count(2)->create();

        $this->getJson('/api/v1/persona')
            ->assertOk()
            ->assertJsonCount(2, 'persona');
    }

    public function test_admin_scoped_no_puede_listar_personas(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'admin']);

        $this->getJson('/api/v1/persona')->assertStatus(403);
    }

    /**
     * Regresión: antes cualquier Persona autenticada (un participante
     * común) podía listar y ver los datos de CUALQUIER otra Persona vía
     * este mismo endpoint — sin scoping de rol alguno. Ahora la ruta está
     * detrás de auth:admins, así que un token de Persona ni siquiera pasa
     * el guard (401, no llega a assertIsSuperAdmin() en el controller).
     */
    public function test_persona_comun_no_puede_listar_ni_ver_otras_personas(): void
    {
        $this->actingAsPersona();
        $otra = Persona::factory()->create();

        $this->getJson('/api/v1/persona')->assertStatus(401);
        $this->getJson("/api/v1/persona/{$otra->id}")->assertStatus(401);
    }

    public function test_super_admin_puede_crear_persona(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $this->postJson('/api/v1/persona', $this->datosValidos())
            ->assertCreated()
            ->assertJsonPath('persona.nombre', 'Ana')
            ->assertJsonPath('persona.email', 'ana.prueba@test.net')
            ->assertJsonMissingPath('persona.password')
            ->assertJsonMissingPath('persona.token');

        $this->assertDatabaseHas('personas', [
            'email' => 'ana.prueba@test.net',
            'correo' => 'ana.prueba@test.net',
            'nombre' => 'Ana',
            'numero_documento' => '12345678',
        ]);
    }

    public function test_crear_persona_sin_password_genera_una_aleatoria(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $datos = $this->datosValidos();
        unset($datos['password']);

        $this->postJson('/api/v1/persona', $datos)->assertCreated();

        $persona = Persona::where('email', 'ana.prueba@test.net')->firstOrFail();
        $this->assertNotEmpty($persona->password);
    }

    public function test_crear_persona_exige_campos_obligatorios(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $this->postJson('/api/v1/persona', [])->assertStatus(422);
    }

    public function test_crear_persona_rechaza_email_duplicado(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        Persona::factory()->create(['email' => 'ana.prueba@test.net']);

        $this->postJson('/api/v1/persona', $this->datosValidos())->assertStatus(422);
    }

    public function test_super_admin_puede_actualizar_persona_sin_tocar_password(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $persona = Persona::factory()->create(['nombre' => 'Nombre Viejo']);
        $passwordAntes = $persona->password;

        $this->putJson("/api/v1/persona/{$persona->id}", ['nombre' => 'Nombre Nuevo'])
            ->assertOk()
            ->assertJsonPath('persona.nombre', 'Nombre Nuevo');

        $this->assertSame($passwordAntes, $persona->fresh()->password);
    }

    public function test_actualizar_persona_con_password_lo_cambia(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $persona = Persona::factory()->create();
        $passwordAntes = $persona->password;

        $this->putJson("/api/v1/persona/{$persona->id}", ['password' => 'nuevaClave123'])
            ->assertOk();

        $this->assertNotSame($passwordAntes, $persona->fresh()->password);
    }

    public function test_actualizar_email_no_puede_repetir_el_de_otra_persona(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        Persona::factory()->create(['email' => 'ocupado@test.net']);
        $persona = Persona::factory()->create(['email' => 'libre@test.net']);

        $this->putJson("/api/v1/persona/{$persona->id}", ['email' => 'ocupado@test.net'])
            ->assertStatus(422);
    }

    public function test_actualizar_email_de_la_misma_persona_no_rechaza(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $persona = Persona::factory()->create(['email' => 'igual@test.net']);

        $this->putJson("/api/v1/persona/{$persona->id}", ['email' => 'igual@test.net', 'nombre' => 'Actualizada'])
            ->assertOk();
    }

    public function test_super_admin_puede_eliminar_persona(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $persona = Persona::factory()->create();

        $this->deleteJson("/api/v1/persona/{$persona->id}")->assertOk();

        $this->assertDatabaseMissing('personas', ['id' => $persona->id]);
    }

    public function test_persona_comun_no_puede_crear_actualizar_ni_eliminar(): void
    {
        $this->actingAsPersona();
        $otra = Persona::factory()->create();

        $this->postJson('/api/v1/persona', $this->datosValidos())->assertStatus(401);
        $this->putJson("/api/v1/persona/{$otra->id}", ['nombre' => 'Hackeado'])->assertStatus(401);
        $this->deleteJson("/api/v1/persona/{$otra->id}")->assertStatus(401);
    }

    /**
     * persona/me (la cuenta propia, ruta pública) no puede quedar rota
     * por el CRUD admin — ver el reordenamiento de rutas en routes/api.php
     * (GET /persona/me tiene que matchear antes que GET /persona/{persona}).
     */
    public function test_persona_me_sigue_funcionando_para_una_persona_comun(): void
    {
        $persona = $this->actingAsPersona();

        $this->getJson('/api/v1/persona/me')
            ->assertOk()
            ->assertJsonPath('data.id', $persona->id);
    }
}

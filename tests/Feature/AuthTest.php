<?php

namespace Tests\Feature;

use App\Models\Persona;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private array $validPayload;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validPayload = [
            'nombre' => 'Juan',
            'apellido' => 'Perez',
            'alias' => 'jperez',
            'sexo' => 'Masculino',
            'tipo_documento' => 'CI',
            'numero_documento' => '12345678',
            'fecha_nacimiento' => '1990-01-15',
            'correo' => 'juan@example.com',
            'direccion' => 'Calle 123',
            'ciudad' => 'Santa Cruz',
            'telefono' => '33445566',
            'celular' => '71234567',
            'email' => 'juan@example.com',
            'password' => 'secret123',
        ];
    }

    // ==========================================
    // 1.1 REGISTRO DE PERSONA
    // ==========================================

    public function test_register_returns_201_with_token(): void
    {
        $this->postJson('/api/v1/persona/register', $this->validPayload)
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'message', 'data', 'token']);
    }

    public function test_register_creates_persona_in_database(): void
    {
        $this->postJson('/api/v1/persona/register', $this->validPayload);

        $this->assertDatabaseHas('personas', [
            'email' => 'juan@example.com',
            'nombre' => 'Juan',
            'apellido' => 'Perez',
        ]);
    }

    public function test_register_rejects_duplicate_email(): void
    {
        Persona::factory()->create(['email' => 'juan@example.com']);

        $this->postJson('/api/v1/persona/register', $this->validPayload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_register_rejects_missing_required_fields(): void
    {
        $this->postJson('/api/v1/persona/register', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'nombre', 'email', 'apellido', 'alias',
                'sexo', 'tipo_documento', 'numero_documento',
                'fecha_nacimiento', 'correo', 'direccion',
                'ciudad', 'password',
            ]);
    }

    public function test_register_rejects_invalid_email_format(): void
    {
        $this->validPayload['email'] = 'not-an-email';

        $this->postJson('/api/v1/persona/register', $this->validPayload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_register_returns_sanctum_token(): void
    {
        $response = $this->postJson('/api/v1/persona/register', $this->validPayload)
            ->assertCreated();

        $this->assertNotEmpty($response->json('token'));
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    // ==========================================
    // 1.2 LOGIN
    // ==========================================

    public function test_login_returns_200_with_token(): void
    {
        Persona::factory()->create([
            'email' => 'juan@example.com',
            'password' => Hash::make('secret123'),
        ]);

        $this->postJson('/api/v1/persona/login', [
            'email' => 'juan@example.com',
            'password' => 'secret123',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'message', 'data' => ['persona', 'token']]);
    }

    public function test_login_returns_error_for_nonexistent_email(): void
    {
        $this->postJson('/api/v1/persona/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'secret123',
        ])
            ->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['success', 'error']);
    }

    public function test_login_returns_error_for_wrong_password(): void
    {
        Persona::factory()->create([
            'email' => 'juan@example.com',
            'password' => Hash::make('secret123'),
        ]);

        $this->postJson('/api/v1/persona/login', [
            'email' => 'juan@example.com',
            'password' => 'wrongpassword',
        ])
            ->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['success', 'error']);
    }

    public function test_login_rejects_missing_password(): void
    {
        $response = $this->postJson('/api/v1/persona/login', [
            'email' => 'juan@example.com',
        ]);

        $response->assertStatus(422);
    }

    public function test_login_rejects_invalid_email_format(): void
    {
        $response = $this->postJson('/api/v1/persona/login', [
            'email' => 'bad-format',
            'password' => 'secret123',
        ]);

        $response->assertStatus(422);
    }

    // ==========================================
    // 1.3 LOGOUT
    // ==========================================

    public function test_logout_returns_401_without_token(): void
    {
        $this->postJson('/api/v1/persona/logout')
            ->assertStatus(401)
            ->assertJsonPath('message', 'No autorizado');
    }

    public function test_logout_with_valid_token_returns_200(): void
    {
        $persona = Persona::factory()->create();
        $token = $persona->createToken('auth-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/persona/logout')
            ->assertOk();

        $this->assertNotEmpty($response->json('message'));
    }

    public function test_logout_with_invalid_token_returns_401(): void
    {
        $this->withHeader('Authorization', 'Bearer fake-token-12345')
            ->postJson('/api/v1/persona/logout')
            ->assertStatus(401);
    }

    // ==========================================
    // 1.4 LISTAR / VER PERSONAS
    // ==========================================
    // Actualizado 21/08/2026: /persona (index/show/store/update/destroy)
    // pasó a ser un CRUD solo para admins (ver PersonaController::
    // assertIsSuperAdmin() y tests/Feature/PersonaCrudTest.php) — antes
    // cualquier Persona autenticada podía listar/ver los datos de
    // cualquier otra, sin scoping de rol. Estos 3 tests documentaban ese
    // comportamiento viejo; ahora usan un AdminUser super_admin, que es
    // quien de verdad puede usar el endpoint.

    public function test_list_personas_returns_paginated_results(): void
    {
        Persona::factory()->count(3)->create();
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $this->getJson('/api/v1/persona')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'persona', 'pagination']);
    }

    public function test_show_persona_by_id_returns_data(): void
    {
        $persona = Persona::factory()->create(['nombre' => 'Maria']);
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $this->getJson('/api/v1/persona/' . $persona->id)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('persona.nombre', 'Maria');
    }

    public function test_show_persona_returns_404_for_nonexistent(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $this->getJson('/api/v1/persona/99999')
            ->assertNotFound();
    }
}

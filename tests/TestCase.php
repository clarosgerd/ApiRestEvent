<?php

namespace Tests;

use App\Models\AdminUser;
use App\Models\Persona;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Autentica al cliente de test como una Persona vía Sanctum, para las
     * rutas protegidas con auth:sanctum (POST/GET /registrations, DELETE
     * /registrations/{ref}, persona/me, etc). Mismo patrón que ya usaba
     * test_logout_with_valid_token_returns_200 en AuthTest — solo que
     * reutilizable, para no repetirlo en cada test. withHeader() queda
     * seteado para todas las requests siguientes dentro del mismo test.
     *
     * El CRUD admin de `/persona` (21/08/2026) ya NO es de esta clase de
     * sesión — se movió a auth:admins (ver PersonaController), justamente
     * porque antes cualquier Persona autenticada podía listar/ver los
     * datos de cualquier otra vía este mismo helper.
     */
    protected function actingAsPersona(?Persona $persona = null): Persona
    {
        $persona ??= Persona::factory()->create();
        $token = $persona->createToken('test-token')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $token);

        return $persona;
    }

    /**
     * Autentica al cliente de test como un AdminUser vía Sanctum (guard
     * `admins`), para las rutas de EventoController y afines protegidas con
     * auth:admins + AuthorizesEventoScope::assertIsSuperAdmin()/
     * assertCanWriteEvento(). Mismo mecanismo que actingAsPersona() — el
     * guard 'admins' también es Sanctum (ver config/auth.php), solo cambia
     * el modelo/provider.
     */
    protected function actingAsAdmin(?AdminUser $admin = null): AdminUser
    {
        $admin ??= AdminUser::factory()->create();
        $token = $admin->createToken('test-token')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $token);

        return $admin;
    }
}

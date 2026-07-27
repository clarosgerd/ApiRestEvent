<?php

namespace Tests;

use App\Models\Persona;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Autentica al cliente de test como una Persona vía Sanctum, para las
     * rutas protegidas con auth:sanctum (POST/GET /registrations, DELETE
     * /registrations/{ref}, /persona resource, etc). Mismo patrón que ya
     * usaba test_logout_with_valid_token_returns_200 en AuthTest — solo que
     * reutilizable, para no repetirlo en cada test. withHeader() queda
     * seteado para todas las requests siguientes dentro del mismo test.
     */
    protected function actingAsPersona(?Persona $persona = null): Persona
    {
        $persona ??= Persona::factory()->create();
        $token = $persona->createToken('test-token')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $token);

        return $persona;
    }
}

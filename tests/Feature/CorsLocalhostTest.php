<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * CORS para desarrollo local (06/10/2026): el puerto de localhost cambia en cada
 * corrida, así que se acepta por patrón solo si CORS_ALLOW_LOCALHOST está activo.
 */
class CorsLocalhostTest extends TestCase
{
    public function test_acepta_cualquier_puerto_de_localhost_si_esta_activado(): void
    {
        config(['cors.allowed_origins_patterns' => ['#^http://localhost(:\d+)?$#']]);

        $this->withHeaders(['Origin' => 'http://localhost:54744', 'Access-Control-Request-Method' => 'POST'])
            ->options('/api/v1/persona/login')
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:54744');
    }

    public function test_rechaza_un_origen_desconocido(): void
    {
        config(['cors.allowed_origins_patterns' => ['#^http://localhost(:\d+)?$#']]);

        $response = $this->withHeaders(['Origin' => 'https://sitio-ajeno.test', 'Access-Control-Request-Method' => 'POST'])
            ->options('/api/v1/persona/login');

        $this->assertNotSame('https://sitio-ajeno.test', $response->headers->get('Access-Control-Allow-Origin'));
    }
}

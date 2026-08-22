<?php

namespace Tests\Feature\Inscripcion;

use App\Models\Club;
use App\Models\Evento;
use App\Models\FormType;
use App\Models\Persona;
use App\Models\PromoCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Consolidación monolito (22/08/2026), Fase 2a — ex `elascenso-blade`
 * (`routes/web.php` + `App\Http\Controllers\Api\*`/`HomeController`). No son
 * tests de proxy HTTP (elascenso-blade mockeaba `ApiRestEventClient` para
 * probar el forward() correcto) — acá se prueba la delegación in-process
 * real: cada ruta de `routes/inscripcion.php` termina llamando al mismo
 * controller de la API que ya usa `/api/v1/*`, sin salto de red.
 *
 * Fase 2b (registro/pago/webhooks) queda afuera a propósito — ver el
 * comentario de cabecera de routes/inscripcion.php.
 */
class InscripcionRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_sirve_el_shell(): void
    {
        $this->get('/')->assertOk()->assertSee('Pass2Go', false);
    }

    /**
     * Regresión (22/08/2026) — al portar Fase 2 se copiaron las vistas
     * Blade pero no los assets ESTÁTICOS que referencian (`public/css/
     * app.css`, `public/js/*`), que en `elascenso-blade` viven sueltos en
     * su propio `public/` (no pasan por el pipeline de Vite). El resultado
     * era una página sin estilos ni JS, sin que ningún test lo detectara
     * porque `$this->get('/')` no sirve `public/` como un servidor web
     * real — solo un chequeo de archivo en disco lo agarra.
     */
    public function test_assets_estaticos_referenciados_por_home_existen_en_disco(): void
    {
        $this->assertFileExists(public_path('css/app.css'));
        $this->assertFileExists(public_path('js/i18n.js'));
        foreach (['event-list', 'registration', 'review-payment', 'confirmation', 'account', 'results-club'] as $modulo) {
            $this->assertFileExists(public_path("js/modules/{$modulo}.js"));
        }
    }

    public function test_home_con_evento_resuelve_meta_tags_in_process(): void
    {
        $evento = Evento::factory()->create(['nombre' => 'Maratón de Prueba']);

        $this->get('/?evento='.$evento->id)
            ->assertOk()
            ->assertSee('Maratón de Prueba · Pass2Go', false);
    }

    public function test_eventos_index_delega_a_eventocontroller(): void
    {
        Evento::factory()->count(2)->create();

        $this->getJson('/eventos')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'eventos');
    }

    public function test_eventos_show_delega_y_404_si_no_existe(): void
    {
        $evento = Evento::factory()->create();

        $this->getJson('/eventos/'.$evento->id)
            ->assertOk()
            ->assertJsonPath('eventos.id', $evento->id);

        $this->getJson('/eventos/999999')->assertNotFound();
    }

    public function test_agenda_pdf_e_ics_delegan_a_eventocontroller(): void
    {
        $evento = Evento::factory()->create();

        $this->get('/eventos/'.$evento->id.'/agenda.pdf')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->get('/eventos/'.$evento->id.'/agenda.ics')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
    }

    public function test_resultados_evento_requiere_auth(): void
    {
        $evento = Evento::factory()->create();

        $this->getJson('/eventos/'.$evento->id.'/resultados')->assertStatus(401);

        $persona = $this->actingComoPersona();
        $this->getJson('/eventos/'.$evento->id.'/resultados')->assertOk();
    }

    public function test_lista_espera_delega_y_valida_evento(): void
    {
        $evento = Evento::factory()->create();
        $formType = FormType::factory()->create(['event_id' => $evento->id]);

        $this->postJson('/eventos/lista-espera', [
            'evento_id' => 999999,
            'form_types_id' => $formType->id,
            'nombre' => 'Ana',
            'correo' => 'ana@test.net',
        ])->assertStatus(404);

        $this->postJson('/eventos/lista-espera', [
            'form_types_id' => $formType->id,
            'nombre' => 'Ana',
            'correo' => 'ana@test.net',
        ])->assertStatus(400);
    }

    public function test_persona_login_registro_logout_delegan_a_personacontroller(): void
    {
        $persona = Persona::factory()->create([
            'email' => 'juan@test.net',
            'password' => Hash::make('secret123'),
        ]);

        $this->postJson('/persona/login', ['email' => 'juan@test.net', 'password' => 'wrong'])
            ->assertOk()
            ->assertJsonPath('success', false);

        $login = $this->postJson('/persona/login', ['email' => 'juan@test.net', 'password' => 'secret123'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $token = $login->json('data.token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/persona/logout')
            ->assertOk();
    }

    public function test_club_login_landing_logout_delegan_a_clubcontroller(): void
    {
        $club = Club::create([
            'nombre' => 'Club Test',
            'email' => 'club@test.net',
            'password' => Hash::make('secret123'),
            'activo' => true,
        ]);

        $this->postJson('/club/login', ['email' => 'club@test.net', 'password' => 'wrong'])
            ->assertStatus(401);

        $login = $this->postJson('/club/login', ['email' => 'club@test.net', 'password' => 'secret123'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $token = $login->json('data.token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/club/landing')
            ->assertOk()
            ->assertJsonPath('club.id', $club->id);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/club/logout')
            ->assertOk();
    }

    public function test_promo_delega_a_promocodecontroller(): void
    {
        $evento = Evento::factory()->create();
        PromoCode::factory()->create(['event_id' => $evento->id, 'promo_code' => 'DESCUENTO20']);

        $this->getJson('/promo/'.$evento->id.'/DESCUENTO20')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_tipo_cambio_no_llama_a_apirestevent(): void
    {
        Http::fake(['open.er-api.com/*' => Http::response([
            'result' => 'success',
            'rates' => ['USD' => 0.1437, 'BRL' => 0.72],
        ])]);

        $this->getJson('/tipo-cambio')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('rates.USD', 0.1437);
    }

    public function test_registro_lookup_delega_a_registrationcontroller(): void
    {
        $this->postJson('/registro/lookup', [])->assertStatus(422);

        $this->postJson('/registro/lookup', [
            'email' => 'nadie@test.net',
            'password' => 'secret123',
            'evento_id' => 1,
            'form_type_id' => 1,
        ])->assertStatus(401);
    }

    public function test_registros_mias_y_resultados_mios_requieren_auth(): void
    {
        $this->getJson('/registros/mias')->assertStatus(401);
        $this->getJson('/resultados/mios')->assertStatus(401);

        $this->actingComoPersona();
        $this->getJson('/registros/mias')->assertOk();
        $this->getJson('/resultados/mios')->assertOk();
    }

    public function test_alias_api_php_apuntan_a_los_mismos_controllers(): void
    {
        $evento = Evento::factory()->create();

        $this->getJson('/api/eventos.php?id='.$evento->id)
            ->assertOk()
            ->assertJsonPath('eventos.id', $evento->id);

        $this->getJson('/api/eventos.php')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    private function actingComoPersona(): Persona
    {
        $persona = Persona::factory()->create();
        $token = $persona->createToken('test-token')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer '.$token);

        return $persona;
    }
}

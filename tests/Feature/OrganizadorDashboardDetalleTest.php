<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ciudad;
use App\Models\Evento;
use App\Models\FormType;
use App\Models\Organizador;
use App\Models\Pais;
use App\Models\Participante;
use App\Models\Registration;
use App\Models\SubtipoEvento;
use App\Models\TipoEvento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Detalle de inscritos con buscador en el dashboard público del organizador
 * (07/09/2026, pedido del usuario: al hacer clic en la tarjeta "Pagados" —
 * u otro estado — poder buscar por documento/nombre/apellido/correo). Ver
 * OrganizadorDashboardController::detalle(). Mismo criterio de firma que
 * exportCsv(): cubre solo `evento`, ignorando los filtros.
 */
class OrganizadorDashboardDetalleTest extends TestCase
{
    use RefreshDatabase;

    private Evento $evento;

    private FormType $formType;

    private Category $categoria;

    protected function setUp(): void
    {
        parent::setUp();

        $pais = Pais::factory()->create();
        $ciudad = Ciudad::factory()->create(['pais_id' => $pais->id]);
        $organizador = Organizador::factory()->create();
        $tipoEvento = TipoEvento::factory()->create();
        $subtipoEvento = SubtipoEvento::factory()->create(['tipo_evento_id' => $tipoEvento->id]);

        $this->evento = Evento::factory()->create([
            'organizador_id' => $organizador->id,
            'tipo_evento_id' => $tipoEvento->id,
            'subtipo_evento_id' => $subtipoEvento->id,
            'pais_id' => $pais->id,
            'ciudad_id' => $ciudad->id,
        ]);

        $this->formType = FormType::factory()->create(['event_id' => $this->evento->id]);
        $this->categoria = Category::factory()->create(['event_id' => $this->evento->id, 'name' => '5K', 'price' => 50]);
    }

    private function crearInscripcion(array $overrides = []): Participante
    {
        $registration = Registration::create([
            'referencia' => 'REF' . rand(100000, 999999),
            'fecha' => now(),
            'evento_id' => $this->evento->id,
            'form_types_id' => $this->formType->id,
            'evento_nombre' => $this->evento->nombre,
            'tipo_pago' => 'EFECTIVO',
            'pago_status' => $overrides['pago_status'] ?? 'paid',
        ]);
        unset($overrides['pago_status']);

        return Participante::create(array_merge([
            'registration_id' => $registration->id,
            'nombre' => 'Ana', 'apellido' => 'Prueba', 'genero' => 'Femenino',
            'tipo_documento' => 'DNI', 'numero_documento' => (string) rand(1000000, 9999999),
            'fecha_nacimiento' => '1995-01-01', 'edad' => 30,
            'correo' => 'ana' . rand(1, 999999) . '@test.net', 'direccion' => 'x', 'ciudad' => 'x', 'telefono' => '123',
            'categoria' => $this->categoria->id,
            'precio_categoria' => 50, 'subtotal' => 50,
        ], $overrides));
    }

    public function test_rechaza_firma_invalida(): void
    {
        $this->get("/organizador/evento/{$this->evento->id}/detalle")->assertStatus(403);
    }

    public function test_lista_todos_los_participantes_sin_filtros(): void
    {
        $this->crearInscripcion();
        $this->crearInscripcion(['pago_status' => 'pending']);

        $url = URL::signedRoute('organizador.dashboard.detalle', ['evento' => $this->evento->id]);
        $response = $this->get($url)->assertOk();

        $response->assertSee('2 inscrito(s) en total');
    }

    public function test_filtra_por_pago_status(): void
    {
        $this->crearInscripcion(['pago_status' => 'paid']);
        $this->crearInscripcion(['pago_status' => 'pending']);
        $this->crearInscripcion(['pago_status' => 'pending']);

        $url = URL::signedRoute('organizador.dashboard.detalle', ['evento' => $this->evento->id]);
        $response = $this->get($url . '&pago_status=pending')->assertOk();

        $response->assertSee('2 inscrito(s) en total');
    }

    public function test_busca_por_numero_documento(): void
    {
        $this->crearInscripcion(['numero_documento' => '12345678']);
        $this->crearInscripcion(['numero_documento' => '99999999']);

        $url = URL::signedRoute('organizador.dashboard.detalle', ['evento' => $this->evento->id]);
        $response = $this->get($url . '&search=1234')->assertOk();

        $response->assertSee('12345678');
        $response->assertDontSee('99999999');
    }

    public function test_busca_por_apellido(): void
    {
        $this->crearInscripcion(['apellido' => 'Gutierrez']);
        $this->crearInscripcion(['apellido' => 'Fernandez']);

        $url = URL::signedRoute('organizador.dashboard.detalle', ['evento' => $this->evento->id]);
        $response = $this->get($url . '&search=gutier')->assertOk();

        $response->assertSee('Gutierrez');
        $response->assertDontSee('Fernandez');
    }

    public function test_busca_por_correo(): void
    {
        $this->crearInscripcion(['correo' => 'unico-buscable@test.net']);
        $this->crearInscripcion(['correo' => 'otro@test.net']);

        $url = URL::signedRoute('organizador.dashboard.detalle', ['evento' => $this->evento->id]);
        $response = $this->get($url . '&search=unico-buscable')->assertOk();

        $response->assertSee('unico-buscable@test.net');
        $response->assertDontSee('otro@test.net');
    }

    public function test_firma_ignora_los_filtros(): void
    {
        // La firma se generó SOLO con `evento` — agregar filtros a mano
        // (como hace la vista al armar los links) no debe invalidarla.
        $this->crearInscripcion(['pago_status' => 'paid']);

        $url = URL::signedRoute('organizador.dashboard.detalle', ['evento' => $this->evento->id]);
        $this->get($url . '&pago_status=paid&search=algo&categoria=' . $this->categoria->id . '&page=1')
            ->assertOk();
    }

    public function test_dashboard_principal_enlaza_a_la_tarjeta_pagados(): void
    {
        $this->crearInscripcion(['pago_status' => 'paid']);

        $url = URL::signedRoute('organizador.dashboard', ['evento' => $this->evento->id]);
        $response = $this->get($url)->assertOk();

        $response->assertSee('pago_status=paid', false);
    }
}

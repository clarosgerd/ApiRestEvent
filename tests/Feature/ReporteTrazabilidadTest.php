<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Category;
use App\Models\Ciudad;
use App\Models\Evento;
use App\Models\FormType;
use App\Models\Organizador;
use App\Models\Pais;
use App\Models\PagoAdicionalInscripcion;
use App\Models\Participante;
use App\Models\ParticipanteTallerSesion;
use App\Models\Registration;
use App\Models\RegistrationTotal;
use App\Models\SesionCongreso;
use App\Models\Souvenir;
use App\Models\SouvenirParticipante;
use App\Models\SubtipoEvento;
use App\Models\Taller;
use App\Models\TipoEvento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reporte de trazabilidad de inscripciones (admin general, cross-evento,
 * 10/09/2026) — ver brain/api_rest_event/PLAN-REPORTE-TRAZABILIDAD-10092026.md
 * y App\Support\ReporteTrazabilidadData.
 */
class ReporteTrazabilidadTest extends TestCase
{
    use RefreshDatabase;

    private function crearEvento(string $tipoFormulario = 'deportivo'): array
    {
        $pais = Pais::factory()->create();
        $ciudad = Ciudad::factory()->create(['pais_id' => $pais->id]);
        $organizador = Organizador::factory()->create();
        $tipoEvento = TipoEvento::factory()->create();
        $subtipoEvento = SubtipoEvento::factory()->create(['tipo_evento_id' => $tipoEvento->id]);

        $evento = Evento::factory()->create([
            'organizador_id' => $organizador->id,
            'tipo_evento_id' => $tipoEvento->id,
            'subtipo_evento_id' => $subtipoEvento->id,
            'pais_id' => $pais->id,
            'ciudad_id' => $ciudad->id,
        ]);
        $formType = FormType::factory()->create(['event_id' => $evento->id, 'tipo' => $tipoFormulario]);
        $categoria = Category::factory()->create(['event_id' => $evento->id, 'price' => 100]);

        return [$evento, $formType, $categoria];
    }

    private function crearRegistration(Evento $evento, FormType $formType, array $overrides = []): Registration
    {
        $registration = Registration::create(array_merge([
            'referencia' => 'REF'.rand(100000, 999999),
            'fecha' => now(),
            'evento_id' => $evento->id,
            'form_types_id' => $formType->id,
            'evento_nombre' => $evento->nombre,
            'tipo_pago' => 'sip',
            'pago_status' => 'paid',
        ], $overrides));

        RegistrationTotal::create([
            'registration_id' => $registration->id,
            'inscripcion' => 100, 'donacion' => 0, 'souvenirs' => 0, 'talleres' => 0,
            'fee' => 5, 'descuento' => 0, 'descuento_registrante' => 0, 'grand_total' => 105,
        ]);

        return $registration;
    }

    private function crearParticipante(Registration $registration, Category $categoria, array $overrides = []): Participante
    {
        return Participante::create(array_merge([
            'registration_id' => $registration->id,
            'nombre' => 'Nombre'.rand(1000, 9999), 'apellido' => 'Apellido',
            'genero' => 'Femenino', 'tipo_documento' => 'DNI',
            'numero_documento' => (string) rand(1000000, 9999999),
            'fecha_nacimiento' => '1990-01-01', 'edad' => 35,
            'correo' => 'test'.rand(1000, 9999).'@test.net', 'direccion' => 'x', 'ciudad' => 'x', 'telefono' => '123',
            'categoria' => $categoria->id, 'precio_categoria' => 100, 'subtotal' => 100,
            'polera' => 'No shirt',
        ], $overrides));
    }

    public function test_requiere_super_admin(): void
    {
        [$evento, $formType] = $this->crearEvento();
        $adminScoped = AdminUser::factory()->scopedTo($evento->id)->create();
        $this->actingAsAdmin($adminScoped);

        $response = $this->getJson('/api/v1/reporte-trazabilidad');

        $response->assertStatus(403);
    }

    public function test_sin_autenticacion_es_rechazado(): void
    {
        $response = $this->getJson('/api/v1/reporte-trazabilidad');

        $response->assertStatus(401);
    }

    public function test_lista_inscripciones_para_super_admin(): void
    {
        [$evento, $formType, $categoria] = $this->crearEvento();
        $registration = $this->crearRegistration($evento, $formType);
        $this->crearParticipante($registration, $categoria);

        $this->actingAsAdmin();

        $response = $this->getJson('/api/v1/reporte-trazabilidad');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.referencia', $registration->referencia)
            ->assertJsonPath('data.0.montoInscripcion', 105)
            ->assertJsonPath('data.0.cantidadParticipantes', 1)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_filtra_por_evento_id(): void
    {
        [$evento1, $formType1, $categoria1] = $this->crearEvento();
        [$evento2, $formType2, $categoria2] = $this->crearEvento();
        $r1 = $this->crearRegistration($evento1, $formType1);
        $this->crearParticipante($r1, $categoria1);
        $r2 = $this->crearRegistration($evento2, $formType2);
        $this->crearParticipante($r2, $categoria2);

        $this->actingAsAdmin();

        $response = $this->getJson('/api/v1/reporte-trazabilidad?evento_id='.$evento1->id);

        $response->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.referencia', $r1->referencia);
    }

    public function test_filtra_por_tipo_evento_form_types_tipo(): void
    {
        [$eventoDeportivo, $formTypeDeportivo, $categoriaDeportivo] = $this->crearEvento('deportivo');
        [$eventoCongreso, $formTypeCongreso, $categoriaCongreso] = $this->crearEvento('congreso');
        $rDeportivo = $this->crearRegistration($eventoDeportivo, $formTypeDeportivo);
        $this->crearParticipante($rDeportivo, $categoriaDeportivo);
        $rCongreso = $this->crearRegistration($eventoCongreso, $formTypeCongreso);
        $this->crearParticipante($rCongreso, $categoriaCongreso);

        $this->actingAsAdmin();

        $response = $this->getJson('/api/v1/reporte-trazabilidad?tipo_evento=congreso');

        $response->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.referencia', $rCongreso->referencia)
            ->assertJsonPath('data.0.formTypeTipo', 'congreso');
    }

    public function test_filtra_por_pago_status(): void
    {
        [$evento, $formType, $categoria] = $this->crearEvento();
        $pagada = $this->crearRegistration($evento, $formType, ['pago_status' => 'paid']);
        $this->crearParticipante($pagada, $categoria);
        $pendiente = $this->crearRegistration($evento, $formType, ['pago_status' => 'pending']);
        $this->crearParticipante($pendiente, $categoria);

        $this->actingAsAdmin();

        $response = $this->getJson('/api/v1/reporte-trazabilidad?pago_status=pending');

        $response->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.referencia', $pendiente->referencia);
    }

    public function test_filtra_por_tipo_pago(): void
    {
        [$evento, $formType, $categoria] = $this->crearEvento();
        $sip = $this->crearRegistration($evento, $formType, ['tipo_pago' => 'sip']);
        $this->crearParticipante($sip, $categoria);
        $multipago = $this->crearRegistration($evento, $formType, ['tipo_pago' => 'multipago']);
        $this->crearParticipante($multipago, $categoria);

        $this->actingAsAdmin();

        $response = $this->getJson('/api/v1/reporte-trazabilidad?tipo_pago=multipago');

        $response->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.referencia', $multipago->referencia);
    }

    public function test_filtra_por_rango_de_fechas(): void
    {
        [$evento, $formType, $categoria] = $this->crearEvento();
        $vieja = $this->crearRegistration($evento, $formType, ['fecha' => now()->subDays(30)]);
        $this->crearParticipante($vieja, $categoria);
        $reciente = $this->crearRegistration($evento, $formType, ['fecha' => now()]);
        $this->crearParticipante($reciente, $categoria);

        $this->actingAsAdmin();

        $response = $this->getJson('/api/v1/reporte-trazabilidad?fecha_desde='.now()->subDays(2)->toDateString());

        $response->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.referencia', $reciente->referencia);
    }

    public function test_busca_por_nombre_apellido_documento_correo_o_referencia(): void
    {
        [$evento, $formType, $categoria] = $this->crearEvento();
        $registration = $this->crearRegistration($evento, $formType);
        $this->crearParticipante($registration, $categoria, [
            'nombre' => 'Estefany', 'apellido' => 'Centellas', 'numero_documento' => '6897549',
            'correo' => 'estefany@buscar.net',
        ]);
        $otra = $this->crearRegistration($evento, $formType);
        $this->crearParticipante($otra, $categoria, ['nombre' => 'Mario', 'apellido' => 'Gumiel']);

        $this->actingAsAdmin();

        $porApellido = $this->getJson('/api/v1/reporte-trazabilidad?search=Centellas');
        $porApellido->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.referencia', $registration->referencia);

        $porReferencia = $this->getJson('/api/v1/reporte-trazabilidad?search='.$otra->referencia);
        $porReferencia->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.referencia', $otra->referencia);
    }

    public function test_filtra_solo_inscripciones_con_adiciones(): void
    {
        [$evento, $formType, $categoria] = $this->crearEvento();
        $conAdicion = $this->crearRegistration($evento, $formType);
        $this->crearParticipante($conAdicion, $categoria);
        PagoAdicionalInscripcion::create([
            'registration_id' => $conAdicion->id, 'referencia' => 'AD-CONADICION',
            'monto' => 45, 'moneda_pago' => 'BOB', 'participantes_payload' => [], 'totales_payload' => [],
            'pago_status' => 'paid', 'paid_at' => now(),
        ]);
        $sinAdicion = $this->crearRegistration($evento, $formType);
        $this->crearParticipante($sinAdicion, $categoria);

        $this->actingAsAdmin();

        $response = $this->getJson('/api/v1/reporte-trazabilidad?tiene_adiciones=1');

        $response->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.referencia', $conAdicion->referencia);
    }

    public function test_pagina_correctamente(): void
    {
        [$evento, $formType, $categoria] = $this->crearEvento();
        for ($i = 0; $i < 3; $i++) {
            $r = $this->crearRegistration($evento, $formType);
            $this->crearParticipante($r, $categoria);
        }

        $this->actingAsAdmin();

        $response = $this->getJson('/api/v1/reporte-trazabilidad?per_page=2&page=2');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.currentPage', 2)
            ->assertJsonPath('meta.lastPage', 2)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.perPage', 2);
    }

    public function test_per_page_esta_topeado_al_maximo(): void
    {
        [$evento, $formType, $categoria] = $this->crearEvento();
        $r = $this->crearRegistration($evento, $formType);
        $this->crearParticipante($r, $categoria);

        $this->actingAsAdmin();

        $response = $this->getJson('/api/v1/reporte-trazabilidad?per_page=99999');

        $response->assertOk()->assertJsonPath('meta.perPage', 100);
    }

    public function test_inscripcion_grupal_es_una_sola_fila_con_participantes_desglosados(): void
    {
        [$evento, $formType, $categoria] = $this->crearEvento();
        $registration = $this->crearRegistration($evento, $formType);
        $this->crearParticipante($registration, $categoria, ['nombre' => 'Uno']);
        $this->crearParticipante($registration, $categoria, ['nombre' => 'Dos']);

        $this->actingAsAdmin();

        $response = $this->getJson('/api/v1/reporte-trazabilidad');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.cantidadParticipantes', 2)
            ->assertJsonCount(2, 'data.0.participantes');
    }

    public function test_monto_adiciones_separa_pagadas_de_pendientes_y_otros_estados(): void
    {
        [$evento, $formType, $categoria] = $this->crearEvento();
        $registration = $this->crearRegistration($evento, $formType);
        $this->crearParticipante($registration, $categoria);

        PagoAdicionalInscripcion::create([
            'registration_id' => $registration->id, 'referencia' => 'AD-PAGADA',
            'monto' => 40, 'moneda_pago' => 'BOB', 'participantes_payload' => [], 'totales_payload' => [],
            'pago_status' => 'paid', 'paid_at' => now(),
        ]);
        PagoAdicionalInscripcion::create([
            'registration_id' => $registration->id, 'referencia' => 'AD-OTRAPAGADA',
            'monto' => 20, 'moneda_pago' => 'BOB', 'participantes_payload' => [], 'totales_payload' => [],
            'pago_status' => 'paid', 'paid_at' => now(),
        ]);
        PagoAdicionalInscripcion::create([
            'registration_id' => $registration->id, 'referencia' => 'AD-PENDIENTE',
            'monto' => 15, 'moneda_pago' => 'BOB', 'participantes_payload' => [], 'totales_payload' => [],
            'pago_status' => 'pending',
        ]);
        PagoAdicionalInscripcion::create([
            'registration_id' => $registration->id, 'referencia' => 'AD-ERROR',
            'monto' => 99, 'moneda_pago' => 'BOB', 'participantes_payload' => [], 'totales_payload' => [],
            'pago_status' => 'error',
        ]);

        $this->actingAsAdmin();

        $response = $this->getJson('/api/v1/reporte-trazabilidad');

        $response->assertOk()
            ->assertJsonPath('data.0.montoAdicionesPagadas', 60)
            ->assertJsonPath('data.0.cantidadAdicionesPendientes', 1)
            ->assertJsonCount(4, 'data.0.adiciones');
    }

    public function test_polera_resuelta_via_souvenir_es_polera_no_el_legacy(): void
    {
        [$evento, $formType, $categoria] = $this->crearEvento();
        $souvenirPolera = Souvenir::factory()->create([
            'form_types_id' => $formType->id, 'price' => 30, 'requiere_talla' => true, 'es_polera' => true,
        ]);
        $registration = $this->crearRegistration($evento, $formType);
        $conPolera = $this->crearParticipante($registration, $categoria, ['polera' => 'No shirt']);
        SouvenirParticipante::create([
            'participante_id' => $conPolera->id, 'souvenir_id' => $souvenirPolera->id,
            'nombre' => $souvenirPolera->name, 'precio' => 30, 'talla' => 'M', 'sexo' => 'Femenino',
        ]);
        $sinPolera = $this->crearParticipante($registration, $categoria, ['polera' => 'No shirt']);

        $this->actingAsAdmin();

        $response = $this->getJson('/api/v1/reporte-trazabilidad');

        $response->assertOk()->assertJsonPath('data.0.tienePoleras', true)
            ->assertJsonPath('data.0.cantidadPoleras', 1);

        $participantes = $response->json('data.0.participantes');
        $filaConPolera = collect($participantes)->firstWhere('numeroDocumento', $conPolera->numero_documento);
        $filaSinPolera = collect($participantes)->firstWhere('numeroDocumento', $sinPolera->numero_documento);
        $this->assertTrue($filaConPolera['tienePolera']);
        $this->assertSame('M', $filaConPolera['tallaPolera']);
        $this->assertFalse($filaSinPolera['tienePolera']);
        $this->assertNull($filaSinPolera['tallaPolera']);
    }

    public function test_tiene_talleres_y_su_detalle(): void
    {
        [$evento, $formType, $categoria] = $this->crearEvento('congreso');
        $taller = Taller::factory()->create(['evento_id' => $evento->id, 'nombre' => 'Bombas Elastoméricas']);
        $sesion = SesionCongreso::factory()->create(['evento_id' => $evento->id, 'taller_id' => $taller->id]);
        $registration = $this->crearRegistration($evento, $formType);
        $participante = $this->crearParticipante($registration, $categoria);
        ParticipanteTallerSesion::create([
            'participante_id' => $participante->id, 'sesion_congreso_id' => $sesion->id, 'taller_id' => $taller->id,
            'unit_price' => 80, 'discount' => 0, 'total' => 80, 'pago_pendiente' => false,
        ]);

        $this->actingAsAdmin();

        $response = $this->getJson('/api/v1/reporte-trazabilidad');

        $response->assertOk()
            ->assertJsonPath('data.0.tieneTalleres', true)
            ->assertJsonPath('data.0.cantidadTalleres', 1)
            ->assertJsonPath('data.0.participantes.0.talleres.0.tallerNombre', 'Bombas Elastoméricas')
            ->assertJsonPath('data.0.participantes.0.talleres.0.monto', 80);
    }

    public function test_registration_sin_participantes_no_rompe_el_reporte(): void
    {
        [$evento, $formType] = $this->crearEvento();
        $registration = $this->crearRegistration($evento, $formType, ['pago_status' => 'pending']);
        // A propósito, sin ningún Participante::create() — simula un alta
        // que nunca se completó.

        $this->actingAsAdmin();

        $response = $this->getJson('/api/v1/reporte-trazabilidad');

        $response->assertOk()
            ->assertJsonPath('data.0.referencia', $registration->referencia)
            ->assertJsonPath('data.0.cantidadParticipantes', 0)
            ->assertJsonPath('data.0.tienePoleras', false)
            ->assertJsonPath('data.0.tieneTalleres', false)
            ->assertJsonCount(0, 'data.0.participantes');
    }

    public function test_filtros_disponibles_expone_solo_valores_realmente_usados(): void
    {
        [$eventoDeportivo, $formTypeDeportivo, $categoria] = $this->crearEvento('deportivo');
        $this->crearRegistration($eventoDeportivo, $formTypeDeportivo, ['tipo_pago' => 'sip']);

        $this->actingAsAdmin();

        $response = $this->getJson('/api/v1/reporte-trazabilidad');

        $response->assertOk();
        $tiposEvento = $response->json('filtrosDisponibles.tiposEvento');
        $tiposPago = $response->json('filtrosDisponibles.tiposPago');

        $this->assertContains('deportivo', $tiposEvento);
        // No se filtró por congreso en este test — el enum completo tiene
        // 22 valores, acá solo debe aparecer lo que existe en la BD.
        $this->assertNotContains('religioso', $tiposEvento);
        $this->assertContains('sip', $tiposPago);
    }
}

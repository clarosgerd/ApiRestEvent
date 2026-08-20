<?php

namespace Tests\Feature;

use App\DTOs\RegistrationDTO;
use App\DTOs\ParticipantDTO;
use App\DTOs\TotalsDTO;
use App\DTOs\ContactoEmergenciaParticipanteDTO;
use App\DTOs\BirthDateDTO;
use App\DTOs\SouvenirParticipanteDTO;
use App\DTOs\TallerSesionDTO;
use App\Actions\CrearInscripcionAction;
use App\Models\Category;
use App\Models\Ciudad;
use App\Models\Evento;
use App\Models\FormType;
use App\Models\Organizador;
use App\Models\Pais;
use App\Models\SesionCongreso;
use App\Models\SubtipoEvento;
use App\Models\Taller;
use App\Models\TipoEvento;
use App\Support\CurrencyResolverData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Precio USD fijo, sin tipo de cambio (19/08/2026) — ver
 * brain/PLAN-PRECIO-USD-FIJO-19082026.md. Modo alternativo a
 * `acepta_usd` con tasa (cubierto en MonedaPagoRegistrationTest, que
 * sigue intacto). Alcance: categoría/inscripción + talleres (extendido el
 * mismo día, ver bloque "Talleres en modo USD fijo" más abajo) — sin
 * souvenirs, donación ni camiseta.
 */
class PrecioUsdFijoTest extends TestCase
{
    use RefreshDatabase;

    private Evento $evento;

    private FormType $formType;

    private Category $categoriaConPrecioUsd;

    private Category $categoriaSinPrecioUsd;

    protected function setUp(): void
    {
        parent::setUp();

        $pais = Pais::factory()->create();
        $ciudad = Ciudad::factory()->create(['pais_id' => $pais->id]);
        $organizador = Organizador::factory()->create();
        $tipo = TipoEvento::factory()->create();
        $subtipo = SubtipoEvento::factory()->create(['tipo_evento_id' => $tipo->id]);

        $this->evento = Evento::factory()->create([
            'organizador_id' => $organizador->id,
            'tipo_evento_id' => $tipo->id,
            'subtipo_evento_id' => $subtipo->id,
            'pais_id' => $pais->id,
            'ciudad_id' => $ciudad->id,
            'acepta_usd' => true,
            'usd_precio_fijo' => true,
            'fee_pct' => 0.05,
        ]);

        $this->formType = FormType::factory()->create([
            'event_id' => $this->evento->id,
            'requiere_categoria' => true,
        ]);

        $this->categoriaConPrecioUsd = Category::factory()->create([
            'event_id' => $this->evento->id,
            'name' => 'Extranjero',
            'price' => 700,
            'price_usd' => 50,
        ]);

        $this->categoriaSinPrecioUsd = Category::factory()->create([
            'event_id' => $this->evento->id,
            'name' => 'Local',
            'price' => 700,
            'price_usd' => null,
        ]);
    }

    private function makeParticipant(Category $categoria, float $donation = 0, float $shirtPrice = 0, array $souvenirs = [], array $talleres = []): ParticipantDTO
    {
        return new ParticipantDTO(
            firstName: 'Ana',
            lastName: 'Prueba',
            alias: 'ana',
            gender: 'Femenino',
            documentType: 'DNI',
            documentNumber: (string) rand(10000000, 99999999),
            shirt: $shirtPrice > 0 ? 'M' : 'No shirt',
            shirtPrice: $shirtPrice,
            birthDate: new BirthDateDTO(1, 1, 1995),
            age: 30,
            email: 'ana'.uniqid().'@test.net',
            address: 'x',
            city: 'x',
            phone: '123',
            emergencyContact: new ContactoEmergenciaParticipanteDTO('Contacto', '7000000', 'Familiar'),
            souvenirs: $souvenirs,
            answers: [],
            category: (string) $categoria->id,
            categoryPrice: (float) $categoria->price,
            donation: $donation,
            promoDiscount: 0,
            promoCode: '',
            subtotal: (float) $categoria->price,
            talleres: $talleres,
        );
    }

    private function makeRegistrationDTO(ParticipantDTO $participant, ?float $totalPagado): RegistrationDTO
    {
        return new RegistrationDTO(
            reference: 'LA-USDFIJO-'.uniqid(),
            date: \Carbon\Carbon::now(),
            eventId: $this->evento->id,
            formId: $this->formType->id,
            eventName: $this->evento->nombre,
            paymentType: 'sip',
            paymentStatus: 'pending',
            payOrderNumber: null,
            totals: new TotalsDTO(700, 0, 0, 0, 35, 0, 0, 735),
            participants: [$participant],
            monedaPago: 'USD',
            tipoCambioAplicado: null,
            totalPagado: $totalPagado,
        );
    }

    public function test_acepta_total_correcto_sin_tasa(): void
    {
        // 50 USD categoria + 5% fee = 52.50, sin tipo_cambio_aplicado.
        $dto = $this->makeRegistrationDTO($this->makeParticipant($this->categoriaConPrecioUsd), 52.50);

        $reg = app(CrearInscripcionAction::class)->handle($dto);

        $this->assertSame('USD', $reg->moneda_pago);
        $reg->refresh();
        $this->assertNull($reg->tipo_cambio_aplicado);
        $this->assertEquals(52.50, (float) $reg->total_pagado);
    }

    public function test_rechaza_total_que_no_coincide(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/no coincide con el precio fijo/');

        $dto = $this->makeRegistrationDTO($this->makeParticipant($this->categoriaConPrecioUsd), 60.00);
        app(CrearInscripcionAction::class)->handle($dto);
    }

    public function test_rechaza_categoria_sin_precio_usd_configurado(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/no tiene precio en USD configurado/');

        $dto = $this->makeRegistrationDTO($this->makeParticipant($this->categoriaSinPrecioUsd), 36.75);
        app(CrearInscripcionAction::class)->handle($dto);
    }

    public function test_rechaza_si_hay_donacion(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/solo la inscripción/');

        $dto = $this->makeRegistrationDTO($this->makeParticipant($this->categoriaConPrecioUsd, donation: 10), 62.50);
        app(CrearInscripcionAction::class)->handle($dto);
    }

    public function test_rechaza_si_hay_camiseta(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/solo la inscripción/');

        $dto = $this->makeRegistrationDTO($this->makeParticipant($this->categoriaConPrecioUsd, shirtPrice: 5), 57.75);
        app(CrearInscripcionAction::class)->handle($dto);
    }

    public function test_rechaza_si_hay_souvenirs(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/solo la inscripción/');

        $souvenir = SouvenirParticipanteDTO::fromArray([
            'id' => 1, 'nombre' => 'Polera', 'precio' => 10,
        ]);
        $dto = $this->makeRegistrationDTO($this->makeParticipant($this->categoriaConPrecioUsd, souvenirs: [$souvenir]), 62.50);
        app(CrearInscripcionAction::class)->handle($dto);
    }

    /**
     * Sanity check — un evento con `acepta_usd=true` pero
     * `usd_precio_fijo=false` (default) sigue usando el camino de tipo de
     * cambio de siempre, sin que este feature lo toque. Cubierto también
     * en MonedaPagoRegistrationTest; acá se repite con el mismo modelo de
     * datos para dejar explícito que ambos modos conviven sin pisarse.
     */
    public function test_evento_sin_usd_precio_fijo_usa_tipo_de_cambio_como_siempre(): void
    {
        $this->evento->update(['usd_precio_fijo' => false]);

        $r = CurrencyResolverData::resolver(700.0, 'USD', 0.144, 100.80);
        $this->assertSame(0.144, $r['tipo_cambio_aplicado']);
        $this->assertSame(100.80, $r['total_pagado']);
    }

    // ────────────────────────────────────────────────────────────
    // Talleres en modo USD fijo (19/08/2026) — antes cualquier taller
    // seleccionado rechazaba la inscripción en este modo (ver el resto de
    // esta clase, decisión original del 19/08). Extendido el mismo día a
    // pedido del usuario: un taller SÍ se puede cobrar en USD fijo si
    // tiene `price_usd` cargado (taller o su sesión, mismo override que
    // el precio en Bs) — ver ResolverPrecioTallerData::unitPriceUsd().
    // ────────────────────────────────────────────────────────────

    private function crearTallerConSesion(?float $precioUsdTaller, ?float $precioUsdSesion = null): SesionCongreso
    {
        $taller = Taller::factory()->create([
            'evento_id' => $this->evento->id,
            'modalidad' => 'OPTIONAL',
            'precio' => 0,
            'price_usd' => $precioUsdTaller,
        ]);

        return SesionCongreso::factory()->create([
            'evento_id' => $this->evento->id,
            'taller_id' => $taller->id,
            'precio' => 0,
            'price_usd' => $precioUsdSesion,
            'cupo' => null,
        ]);
    }

    public function test_suma_taller_con_precio_usd_cargado_en_el_taller(): void
    {
        $this->evento->update(['talleres_con_costo' => true]);
        $sesion = $this->crearTallerConSesion(precioUsdTaller: 15.0);

        $talleres = [new TallerSesionDTO(tallerId: $sesion->taller_id, sesionCongresoId: $sesion->id)];
        // 50 (categoría) + 15 (taller) = 65, +5% fee = 68.25.
        $dto = $this->makeRegistrationDTO(
            $this->makeParticipant($this->categoriaConPrecioUsd, talleres: $talleres),
            68.25
        );

        $reg = app(CrearInscripcionAction::class)->handle($dto);

        $reg->refresh();
        $this->assertNull($reg->tipo_cambio_aplicado);
        $this->assertEquals(68.25, (float) $reg->total_pagado);
    }

    public function test_override_de_sesion_gana_sobre_el_precio_usd_del_taller(): void
    {
        $this->evento->update(['talleres_con_costo' => true]);
        $sesion = $this->crearTallerConSesion(precioUsdTaller: 15.0, precioUsdSesion: 20.0);

        $talleres = [new TallerSesionDTO(tallerId: $sesion->taller_id, sesionCongresoId: $sesion->id)];
        // 50 (categoría) + 20 (override de sesión, no el 15 del taller) = 70, +5% = 73.50.
        $dto = $this->makeRegistrationDTO(
            $this->makeParticipant($this->categoriaConPrecioUsd, talleres: $talleres),
            73.50
        );

        $reg = app(CrearInscripcionAction::class)->handle($dto);

        $reg->refresh();
        $this->assertEquals(73.50, (float) $reg->total_pagado);
    }

    public function test_rechaza_taller_sin_precio_usd_configurado(): void
    {
        $this->evento->update(['talleres_con_costo' => true]);
        $sesion = $this->crearTallerConSesion(precioUsdTaller: null);

        $talleres = [new TallerSesionDTO(tallerId: $sesion->taller_id, sesionCongresoId: $sesion->id)];
        $dto = $this->makeRegistrationDTO(
            $this->makeParticipant($this->categoriaConPrecioUsd, talleres: $talleres),
            52.50
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/no tiene precio USD configurado/');
        app(CrearInscripcionAction::class)->handle($dto);
    }

    public function test_taller_gratis_en_usd_si_talleres_con_costo_apagado(): void
    {
        // talleres_con_costo=false (default del evento en este test) — el
        // taller es gratis en USD igual que en Bs, aunque no tenga
        // price_usd cargado. No debe rechazar.
        $sesion = $this->crearTallerConSesion(precioUsdTaller: null);

        $talleres = [new TallerSesionDTO(tallerId: $sesion->taller_id, sesionCongresoId: $sesion->id)];
        $dto = $this->makeRegistrationDTO(
            $this->makeParticipant($this->categoriaConPrecioUsd, talleres: $talleres),
            52.50
        );

        $reg = app(CrearInscripcionAction::class)->handle($dto);

        $reg->refresh();
        $this->assertEquals(52.50, (float) $reg->total_pagado);
    }

    public function test_fee_usd_respeta_fee_incluye_talleres_apagado(): void
    {
        $this->evento->update(['talleres_con_costo' => true, 'fee_incluye_talleres' => false]);
        $sesion = $this->crearTallerConSesion(precioUsdTaller: 15.0);

        $talleres = [new TallerSesionDTO(tallerId: $sesion->taller_id, sesionCongresoId: $sesion->id)];
        // Fee solo sobre los 50 de categoría (no sobre los 15 del taller):
        // 50*0.05=2.50. Total = 50 + 15 + 2.50 = 67.50.
        $dto = $this->makeRegistrationDTO(
            $this->makeParticipant($this->categoriaConPrecioUsd, talleres: $talleres),
            67.50
        );

        $reg = app(CrearInscripcionAction::class)->handle($dto);

        $reg->refresh();
        $this->assertEquals(67.50, (float) $reg->total_pagado);
    }
}

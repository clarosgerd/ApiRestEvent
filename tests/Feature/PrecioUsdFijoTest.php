<?php

namespace Tests\Feature;

use App\DTOs\RegistrationDTO;
use App\DTOs\ParticipantDTO;
use App\DTOs\TotalsDTO;
use App\DTOs\ContactoEmergenciaParticipanteDTO;
use App\DTOs\BirthDateDTO;
use App\DTOs\SouvenirParticipanteDTO;
use App\Actions\CrearInscripcionAction;
use App\Models\Category;
use App\Models\Ciudad;
use App\Models\Evento;
use App\Models\FormType;
use App\Models\Organizador;
use App\Models\Pais;
use App\Models\SubtipoEvento;
use App\Models\TipoEvento;
use App\Support\CurrencyResolverData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Precio USD fijo, sin tipo de cambio (19/08/2026) — ver
 * brain/PLAN-PRECIO-USD-FIJO-19082026.md. Modo alternativo a
 * `acepta_usd` con tasa (cubierto en MonedaPagoRegistrationTest, que
 * sigue intacto). Alcance: solo categoría/inscripción, sin souvenirs,
 * talleres, donación ni camiseta.
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

    private function makeParticipant(Category $categoria, float $donation = 0, float $shirtPrice = 0, array $souvenirs = []): ParticipantDTO
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
            talleres: [],
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
}

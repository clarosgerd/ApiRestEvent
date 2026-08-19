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
use App\Models\Registration;
use App\Models\SubtipoEvento;
use App\Models\TipoEvento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Inscripción en BOB y USD (18/08/2026) — ver
 * brain/PLAN-INSCRIPCION-BOB-USD-IMPLEMENTACION.md. Cubre el camino
 * end-to-end vía CrearInscripcionAction.handle() (que es lo que el
 * controller llama) — verificar que el snapshot se persiste
 * correctamente y que los rechazos del CurrencyResolverData saltan
 * antes del commit.
 */
class MonedaPagoRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private Evento $eventoConUsd;
    private Evento $eventoBobOnly;
    private FormType $formType;
    private Category $categoria;

    protected function setUp(): void
    {
        parent::setUp();

        $pais = Pais::factory()->create();
        $ciudad = Ciudad::factory()->create(['pais_id' => $pais->id]);
        $organizador = Organizador::factory()->create();
        $tipo = TipoEvento::factory()->create();
        $subtipo = SubtipoEvento::factory()->create(['tipo_evento_id' => $tipo->id]);

        $this->eventoConUsd = Evento::factory()->create([
            'organizador_id' => $organizador->id,
            'tipo_evento_id' => $tipo->id,
            'subtipo_evento_id' => $subtipo->id,
            'pais_id' => $pais->id,
            'ciudad_id' => $ciudad->id,
            'acepta_usd' => true,
        ]);

        $this->eventoBobOnly = Evento::factory()->create([
            'organizador_id' => $organizador->id,
            'tipo_evento_id' => $tipo->id,
            'subtipo_evento_id' => $subtipo->id,
            'pais_id' => $pais->id,
            'ciudad_id' => $ciudad->id,
            'acepta_usd' => false,
        ]);

        $this->formType = FormType::factory()->create([
            'event_id' => $this->eventoConUsd->id,
            'requiere_categoria' => true,
            'precio_base' => 100,
        ]);

        $this->categoria = Category::factory()->create([
            'event_id' => $this->eventoConUsd->id,
            'name' => 'General',
            'price' => 100,
        ]);
    }

    private function makeParticipant(string $doc): ParticipantDTO
    {
        return new ParticipantDTO(
            firstName: 'Ana',
            lastName: 'Prueba',
            alias: 'ana',
            gender: 'Femenino',
            documentType: 'DNI',
            documentNumber: $doc,
            shirt: 'No shirt',
            shirtPrice: 0,
            birthDate: new BirthDateDTO(1, 1, 1995),
            age: 30,
            email: 'ana'.uniqid().'@test.net',
            address: 'x',
            city: 'x',
            phone: '123',
            emergencyContact: new ContactoEmergenciaParticipanteDTO('Contacto', '7000000', 'Familiar'),
            souvenirs: [],
            answers: [],
            category: (string) $this->categoria->id,
            categoryPrice: 100,
            donation: 0,
            promoDiscount: 0,
            promoCode: '',
            subtotal: 100,
            // Congresos con talleres (18/08/2026) — sin talleres por defecto
            // en este test (el feature se prueba en TallerSeleccionInscripcionTest).
            talleres: [],
        );
    }

    private function makeRegistrationDTO(Evento $evento, string $doc, ?string $moneda = null, ?float $tasa = null, ?float $totalPagado = null): RegistrationDTO
    {
        return new RegistrationDTO(
            reference: 'LA-CURR-'.uniqid(),
            date: \Carbon\Carbon::now(),
            eventId: $evento->id,
            formId: $this->formType->id,
            eventName: $evento->nombre,
            paymentType: 'sip',
            paymentStatus: 'pending',
            payOrderNumber: null,
            totals: new TotalsDTO(100, 0, 0, 0, 5, 0, 0, 105),
            participants: [$this->makeParticipant($doc)],
            monedaPago: $moneda,
            tipoCambioAplicado: $tasa,
            totalPagado: $totalPagado,
        );
    }

    public function test_inscripcion_bob_sin_snapshot_se_persiste_como_bob(): void
    {
        // Un evento con acepta_usd=true también acepta BOB (es el default).
        // El participante elige BOB explícito (sin campos USD).
        $dto = $this->makeRegistrationDTO($this->eventoConUsd, '12345678');

        $reg = app(CrearInscripcionAction::class)->handle($dto);

        $this->assertSame('BOB', $reg->moneda_pago);
        // Recargar desde BD para evitar casts que conviertan null a '0.00'.
        $reg->refresh();
        $this->assertNull($reg->tipo_cambio_aplicado);
        $this->assertNull($reg->total_pagado);
    }

    public function test_inscripcion_bob_explicita_persiste_sin_tasa(): void
    {
        $dto = $this->makeRegistrationDTO($this->eventoConUsd, '12345678', 'BOB');

        $reg = app(CrearInscripcionAction::class)->handle($dto);

        $this->assertSame('BOB', $reg->moneda_pago);
        $this->assertNull($reg->tipo_cambio_aplicado);
    }

    public function test_inscripcion_usd_persiste_snapshot_correcto(): void
    {
        $dto = $this->makeRegistrationDTO(
            $this->eventoConUsd,
            '12345678',
            'USD',
            0.144,
            15.12 // 105 BOB * 0.144 = 15.12 USD
        );

        $reg = app(CrearInscripcionAction::class)->handle($dto);

        $this->assertSame('USD', $reg->moneda_pago);
        $this->assertEquals(0.144, (float) $reg->tipo_cambio_aplicado);
        $this->assertEquals(15.12, (float) $reg->total_pagado);
    }

    public function test_inscripcion_usd_con_evento_bob_only_rechaza(): void
    {
        $dto = $this->makeRegistrationDTO(
            $this->eventoBobOnly,
            '12345678',
            'USD',
            0.144,
            15.12
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/solo acepta pago en BOB/');
        app(CrearInscripcionAction::class)->handle($dto);
    }

    public function test_inscripcion_usd_con_snapshot_fuera_de_tolerancia_rechaza(): void
    {
        // 105 BOB * 0.144 = 15.12 USD. Mandamos 20.00 (drift enorme).
        $dto = $this->makeRegistrationDTO(
            $this->eventoConUsd,
            '12345678',
            'USD',
            0.144,
            20.00
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/no coincide con el grand_total/');
        app(CrearInscripcionAction::class)->handle($dto);
    }

    public function test_inscripcion_usd_sin_snapshot_rechaza(): void
    {
        $dto = $this->makeRegistrationDTO(
            $this->eventoConUsd,
            '12345678',
            'USD',
            null,
            null
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/snapshot de tasa/');
        app(CrearInscripcionAction::class)->handle($dto);
    }

    public function test_inscripcion_usd_drift_dentro_de_tolerancia_pasa(): void
    {
        // 105 BOB * 0.144 = 15.12 USD. Tolerancia 2% de 15.12 = 0.3024.
        // Mandamos 15.40 (delta 0.28) — pasa.
        $dto = $this->makeRegistrationDTO(
            $this->eventoConUsd,
            '12345678',
            'USD',
            0.144,
            15.40
        );

        $reg = app(CrearInscripcionAction::class)->handle($dto);
        $this->assertSame('USD', $reg->moneda_pago);
        $this->assertEquals(15.40, (float) $reg->total_pagado);
    }
}
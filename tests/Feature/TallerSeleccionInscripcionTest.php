<?php

namespace Tests\Feature;

use App\DTOs\ParticipantDTO;
use App\DTOs\RegistrationDTO;
use App\DTOs\TotalsDTO;
use App\DTOs\ContactoEmergenciaParticipanteDTO;
use App\DTOs\BirthDateDTO;
use App\DTOs\TallerSesionDTO;
use App\Models\Category;
use App\Models\Ciudad;
use App\Models\Evento;
use App\Models\FormType;
use App\Models\Organizador;
use App\Models\Pais;
use App\Models\Participante;
use App\Models\ParticipanteTallerSesion;
use App\Models\Registration;
use App\Models\SesionCongreso;
use App\Models\SubtipoEvento;
use App\Models\Taller;
use App\Models\TipoEvento;
use App\Support\Taller\ValidarSeleccionesTallerAction;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cobertura de las reglas de selección de talleres en una inscripción
 * (18/08/2026) — ver
 * brain/PLAN-CONGRESOS-TALLERES-HORARIOS-IMPLEMENTACION.md §1.5:
 *   - Duplicado por participante
 *   - Solape horario (algoritmo del plan §6)
 *   - Capacidad transaccional
 *   - Talleres REQUIRED cubiertos
 *
 * Tests de integración end-to-end vía la API (POST /registrations)
 * viven en otros archivos; este se concentra en la lógica pura del
 * ValidarSeleccionesTallerAction para que sea fácil de leer y los
 * casos se mantengan rápidos.
 */
class TallerSeleccionInscripcionTest extends TestCase
{
    use RefreshDatabase;

    private Evento $evento;
    private FormType $formType;
    private Category $categoria;
    private Taller $tallerEtica;
    private Taller $tallerIA;
    private SesionCongreso $sesionEticaManana;
    private SesionCongreso $sesionEticaTarde;
    private SesionCongreso $sesionIA;

    protected function setUp(): void
    {
        parent::setUp();

        $pais = Pais::factory()->create();
        $ciudad = Ciudad::factory()->create(['pais_id' => $pais->id]);
        $organizador = Organizador::factory()->create();
        $tipo = TipoEvento::factory()->create();
        $subtipo = SubtipoEvento::factory()->create(['tipo_evento_id' => $tipo->id]);

        $this->evento = Evento::factory()->create([
            'organizador_id'    => $organizador->id,
            'tipo_evento_id'    => $tipo->id,
            'subtipo_evento_id' => $subtipo->id,
            'pais_id'           => $pais->id,
            'ciudad_id'         => $ciudad->id,
            'talleres_con_costo' => true,
        ]);
        $this->formType = FormType::factory()->create([
            'event_id' => $this->evento->id,
            'requiere_categoria' => true,
            'precio_base' => 100,
        ]);
        $this->categoria = Category::factory()->create([
            'event_id' => $this->evento->id,
            'name' => 'General',
            'price' => 100,
        ]);

        $this->tallerEtica = Taller::factory()->create([
            'evento_id' => $this->evento->id,
            'nombre' => 'Ética profesional',
            'modalidad' => 'REQUIRED',
            'precio' => 50,
        ]);
        $this->tallerIA = Taller::factory()->create([
            'evento_id' => $this->evento->id,
            'nombre' => 'IA aplicada',
            'modalidad' => 'OPTIONAL',
            'precio' => 75,
        ]);

        $this->sesionEticaManana = SesionCongreso::factory()->create([
            'evento_id' => $this->evento->id,
            'taller_id' => $this->tallerEtica->id,
            'titulo' => 'Ética mañana',
            'fecha' => '2026-09-18',
            'hora_inicio' => '09:00:00',
            'hora_fin' => '11:00:00',
            'cupo' => 30,
        ]);
        $this->sesionEticaTarde = SesionCongreso::factory()->create([
            'evento_id' => $this->evento->id,
            'taller_id' => $this->tallerEtica->id,
            'titulo' => 'Ética tarde',
            'fecha' => '2026-09-18',
            'hora_inicio' => '14:00:00',
            'hora_fin' => '16:00:00',
            'cupo' => 30,
        ]);
        $this->sesionIA = SesionCongreso::factory()->create([
            'evento_id' => $this->evento->id,
            'taller_id' => $this->tallerIA->id,
            'titulo' => 'IA bloque único',
            'fecha' => '2026-09-18',
            'hora_inicio' => '10:30:00', // solapa con la mañana
            'hora_fin' => '12:00:00',
            'cupo' => 25,
        ]);
    }

    private function makeParticipantDTO(array $talleres = []): ParticipantDTO
    {
        return new ParticipantDTO(
            firstName: 'Ana',
            lastName: 'Prueba',
            alias: 'ana',
            gender: 'Femenino',
            documentType: 'DNI',
            documentNumber: (string) rand(10000000, 99999999),
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
            talleres: array_map(
                fn (array $t) => new TallerSesionDTO($t['taller_id'], $t['sesion_id']),
                $talleres
            ),
        );
    }

    private function makeRegistrationDTO(ParticipantDTO $p): RegistrationDTO
    {
        return new RegistrationDTO(
            reference: 'LA-TEST-'.uniqid(),
            date: Carbon::now(),
            eventId: $this->evento->id,
            formId: $this->formType->id,
            eventName: $this->evento->nombre,
            paymentType: 'QR',
            paymentStatus: 'pending',
            payOrderNumber: null,
            totals: new TotalsDTO(100, 0, 0, 0, 5, 0, 0, 105),
            participants: [$p],
        );
    }

    public function test_seleccion_valida_de_required_y_optional_pasa(): void
    {
        // Ética mañana 09–11 e IA tarde 14–16 (no solapan — la IA está en
        // una franja horaria posterior). El setup por defecto pone la IA en
        // 10:30–12:00 que sí solapa, así que para este test usamos
        // explícitamente la sesión de la tarde.
        $sesionIATarde = SesionCongreso::factory()->create([
            'evento_id' => $this->evento->id,
            'taller_id' => $this->tallerIA->id,
            'titulo' => 'IA bloque tarde',
            'fecha' => '2026-09-18',
            'hora_inicio' => '14:00:00',
            'hora_fin' => '16:00:00',
            'cupo' => 25,
        ]);

        $p = $this->makeParticipantDTO([
            ['taller_id' => $this->tallerEtica->id, 'sesion_id' => $this->sesionEticaManana->id],
            ['taller_id' => $this->tallerIA->id, 'sesion_id' => $sesionIATarde->id],
        ]);

        // No debe lanzar excepción — primero valida pertenencia/duplicado/solape,
        // luego capacidad (con el cupo 25/30 todavía hay lugar).
        ValidarSeleccionesTallerAction::run($this->makeRegistrationDTO($p));
        ValidarSeleccionesTallerAction::runCapacidad($this->makeRegistrationDTO($p));
        ValidarSeleccionesTallerAction::runRequeridos($this->makeRegistrationDTO($p));

        $this->assertTrue(true); // llegó hasta acá sin throw
    }

    public function test_solape_de_horario_en_misma_fecha_rechaza(): void
    {
        // Ética mañana 09–11 vs IA 10:30–12 → solapan.
        $p = $this->makeParticipantDTO([
            ['taller_id' => $this->tallerEtica->id, 'sesion_id' => $this->sesionEticaManana->id],
            ['taller_id' => $this->tallerIA->id, 'sesion_id' => $this->sesionIA->id],
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/Conflicto de horario/');
        ValidarSeleccionesTallerAction::run($this->makeRegistrationDTO($p));
    }

    public function test_horarios_seguidos_no_solapan(): void
    {
        // Ética mañana 09–11 y Ética tarde 14–16 → no solapan (mismo taller).
        $p = $this->makeParticipantDTO([
            ['taller_id' => $this->tallerEtica->id, 'sesion_id' => $this->sesionEticaManana->id],
            ['taller_id' => $this->tallerEtica->id, 'sesion_id' => $this->sesionEticaTarde->id],
        ]);

        ValidarSeleccionesTallerAction::run($this->makeRegistrationDTO($p));
        $this->assertTrue(true);
    }

    public function test_duplicado_de_sesion_para_un_mismo_participante_rechaza(): void
    {
        $p = $this->makeParticipantDTO([
            ['taller_id' => $this->tallerEtica->id, 'sesion_id' => $this->sesionEticaManana->id],
            ['taller_id' => $this->tallerEtica->id, 'sesion_id' => $this->sesionEticaManana->id],
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/misma sesi.+n de taller/');
        ValidarSeleccionesTallerAction::run($this->makeRegistrationDTO($p));
    }

    public function test_taller_required_sin_seleccion_rechaza(): void
    {
        // Solo el opcional; Ética (REQUIRED) sin seleccionar.
        $p = $this->makeParticipantDTO([
            ['taller_id' => $this->tallerIA->id, 'sesion_id' => $this->sesionIA->id],
        ]);

        // runPorParticipante no falla (pasa pertenencia y solape trivial);
        // runRequeridos sí.
        ValidarSeleccionesTallerAction::run($this->makeRegistrationDTO($p));

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/obligatorio/');
        ValidarSeleccionesTallerAction::runRequeridos($this->makeRegistrationDTO($p));
    }

    /**
     * Deshabilitar un taller sin ocultarlo (28/08/2026) — ver
     * PLAN-TALLER-PERMITE-INSCRIPCION-28082026.md. Distinto de `activo`
     * (ese además lo oculta del participante) — acá el taller sigue
     * `activo=true` pero no se puede seleccionar.
     */
    public function test_taller_sin_permite_inscripcion_rechaza_la_seleccion(): void
    {
        $this->tallerIA->update(['permite_inscripcion' => false]);

        $p = $this->makeParticipantDTO([
            ['taller_id' => $this->tallerIA->id, 'sesion_id' => $this->sesionIA->id],
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/no está disponible para inscripción/');
        ValidarSeleccionesTallerAction::run($this->makeRegistrationDTO($p));
    }

    /**
     * Un taller REQUIRED con permite_inscripcion=false no puede exigir su
     * propia selección — nadie podría cumplirla.
     */
    public function test_taller_required_sin_permite_inscripcion_no_bloquea_el_registro(): void
    {
        $this->tallerEtica->update(['permite_inscripcion' => false]);

        // Solo el opcional; Ética (REQUIRED, ahora deshabilitado) sin seleccionar.
        $p = $this->makeParticipantDTO([
            ['taller_id' => $this->tallerIA->id, 'sesion_id' => $this->sesionIA->id],
        ]);

        ValidarSeleccionesTallerAction::run($this->makeRegistrationDTO($p));
        ValidarSeleccionesTallerAction::runRequeridos($this->makeRegistrationDTO($p));

        $this->assertTrue(true); // llegó hasta acá sin throw
    }

    public function test_sesion_no_seleccionable_rechaza(): void
    {
        // Sesión sin taller_id (keynote suelta) — no es seleccionable.
        $keynote = SesionCongreso::factory()->create([
            'evento_id' => $this->evento->id,
            'taller_id' => null,
            'titulo' => 'Keynote',
            'fecha' => '2026-09-18',
            'hora_inicio' => '08:00:00',
            'hora_fin' => '09:00:00',
        ]);

        $p = $this->makeParticipantDTO([
            ['taller_id' => 999, 'sesion_id' => $keynote->id],
        ]);

        $this->expectException(\DomainException::class);
        ValidarSeleccionesTallerAction::run($this->makeRegistrationDTO($p));
    }

    public function test_sesion_de_otro_evento_rechaza(): void
    {
        $pais = Pais::factory()->create();
        $ciudad = Ciudad::factory()->create(['pais_id' => $pais->id]);
        $organizador = Organizador::factory()->create();
        $tipo = TipoEvento::factory()->create();
        $subtipo = SubtipoEvento::factory()->create(['tipo_evento_id' => $tipo->id]);
        $otro = Evento::factory()->create([
            'organizador_id' => $organizador->id,
            'tipo_evento_id' => $tipo->id,
            'subtipo_evento_id' => $subtipo->id,
            'pais_id' => $pais->id,
            'ciudad_id' => $ciudad->id,
        ]);
        $tallerAjeno = Taller::factory()->create(['evento_id' => $otro->id, 'modalidad' => 'OPTIONAL']);
        $sesionAjena = SesionCongreso::factory()->create([
            'evento_id' => $otro->id,
            'taller_id' => $tallerAjeno->id,
            'fecha' => '2026-09-18',
            'hora_inicio' => '08:00:00',
            'hora_fin' => '09:00:00',
        ]);

        $p = $this->makeParticipantDTO([
            ['taller_id' => $tallerAjeno->id, 'sesion_id' => $sesionAjena->id],
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/no pertenece a este evento/');
        ValidarSeleccionesTallerAction::run($this->makeRegistrationDTO($p));
    }

    public function test_cupo_lleno_rechaza_nueva_seleccion(): void
    {
        // Llenar el cupo de la sesión de Ética mañana con selecciones previas.
        for ($i = 0; $i < $this->sesionEticaManana->cupo; $i++) {
            $reg = Registration::factory()->create([
                'evento_id' => $this->evento->id,
                'form_types_id' => $this->formType->id,
                'referencia' => 'LA-CUPO-'.$i,
                'evento_nombre' => $this->evento->nombre,
                'tipo_pago' => 'QR',
                'pago_status' => 'pending',
            ]);
            $part = Participante::create([
                'registration_id' => $reg->id,
                'nombre' => 'P'.$i, 'apellido' => 'X', 'genero' => 'Masculino',
                'tipo_documento' => 'DNI', 'numero_documento' => (string) (10000000 + $i),
                'fecha_nacimiento' => '1990-01-01', 'edad' => 30,
                'correo' => 'p'.$i.'@test.net',
                'direccion' => 'x', 'ciudad' => 'x', 'telefono' => '1',
                'categoria' => (string) $this->categoria->id,
                'subtotal' => 100,
            ]);
            ParticipanteTallerSesion::create([
                'participante_id'    => $part->id,
                'sesion_congreso_id' => $this->sesionEticaManana->id,
                'taller_id'          => $this->tallerEtica->id,
                'unit_price'         => 50,
                'total'              => 50,
            ]);
        }

        $p = $this->makeParticipantDTO([
            ['taller_id' => $this->tallerEtica->id, 'sesion_id' => $this->sesionEticaManana->id],
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/ya no tiene cupos/');
        ValidarSeleccionesTallerAction::runCapacidad($this->makeRegistrationDTO($p));
    }

    /**
     * Cupo secuestrado por inscripciones canceladas (31/08/2026, bug real
     * reportado por el usuario: "los cupos muestran distinta información"
     * entre elascenso/event y admin-eventos) — un pago QR que nunca se
     * completó y terminó `cancelled` (ExpirarInscripcionesPendientesAction
     * nunca borra la fila de participante_taller_sesion, solo cambia
     * `registrations.pago_status`) NO debe seguir ocupando el cupo. Mismo
     * escenario que test_cupo_lleno_rechaza_nueva_seleccion pero con las
     * inscripciones que llenan el cupo en `cancelled` en vez de `pending`.
     */
    public function test_cupo_liberado_por_inscripcion_cancelada_permite_nueva_seleccion(): void
    {
        for ($i = 0; $i < $this->sesionEticaManana->cupo; $i++) {
            $reg = Registration::factory()->create([
                'evento_id' => $this->evento->id,
                'form_types_id' => $this->formType->id,
                'referencia' => 'LA-CANCEL-'.$i,
                'evento_nombre' => $this->evento->nombre,
                'tipo_pago' => 'QR',
                'pago_status' => 'cancelled',
            ]);
            $part = Participante::create([
                'registration_id' => $reg->id,
                'nombre' => 'P'.$i, 'apellido' => 'X', 'genero' => 'Masculino',
                'tipo_documento' => 'DNI', 'numero_documento' => (string) (20000000 + $i),
                'fecha_nacimiento' => '1990-01-01', 'edad' => 30,
                'correo' => 'pcancel'.$i.'@test.net',
                'direccion' => 'x', 'ciudad' => 'x', 'telefono' => '1',
                'categoria' => (string) $this->categoria->id,
                'subtotal' => 100,
            ]);
            ParticipanteTallerSesion::create([
                'participante_id'    => $part->id,
                'sesion_congreso_id' => $this->sesionEticaManana->id,
                'taller_id'          => $this->tallerEtica->id,
                'unit_price'         => 50,
                'total'              => 50,
            ]);
        }

        $p = $this->makeParticipantDTO([
            ['taller_id' => $this->tallerEtica->id, 'sesion_id' => $this->sesionEticaManana->id],
        ]);

        // No debe lanzar — el cupo está "lleno" solo de inscripciones
        // canceladas, así que sigue disponible entero.
        ValidarSeleccionesTallerAction::runCapacidad($this->makeRegistrationDTO($p));
        $this->assertTrue(true);
    }

    /**
     * El punto central del pedido del usuario: `permite_inscripcion=false`
     * NO oculta el taller del participante (a diferencia de `activo`),
     * solo bloquea que se seleccione — ver los 2 tests de arriba para el
     * bloqueo. Este confirma la visibilidad end-to-end vía el endpoint
     * público real (GET /event/{id}, el que consume elascenso/event).
     */
    public function test_taller_sin_permite_inscripcion_sigue_visible_en_get_event(): void
    {
        $this->tallerIA->update(['permite_inscripcion' => false]);

        $response = $this->getJson("/api/v1/event/{$this->evento->id}");

        $response->assertOk();
        $talleres = collect($response->json('eventos.talleres'));
        $tallerIA = $talleres->firstWhere('id', $this->tallerIA->id);

        $this->assertNotNull($tallerIA, 'el taller deshabilitado desapareció de GET /event — no debería.');
        $this->assertFalse($tallerIA['permiteInscripcion']);
    }

    /**
     * Cupo secuestrado por inscripciones canceladas (31/08/2026) — lo que
     * ve el participante en GET /event (TallerSesionResource) tampoco
     * debe contar selecciones de inscripciones `cancelled`, mismo criterio
     * que el resto del fix (ValidarSeleccionesTallerAction, reporte de
     * admin-eventos).
     */
    public function test_disponibles_en_get_event_no_cuenta_inscripciones_canceladas(): void
    {
        $reg = Registration::factory()->create([
            'evento_id' => $this->evento->id,
            'form_types_id' => $this->formType->id,
            'referencia' => 'LA-CANCEL-DISPONIBLES',
            'evento_nombre' => $this->evento->nombre,
            'tipo_pago' => 'QR',
            'pago_status' => 'cancelled',
        ]);
        $part = Participante::create([
            'registration_id' => $reg->id,
            'nombre' => 'Cancelado', 'apellido' => 'X', 'genero' => 'Masculino',
            'tipo_documento' => 'DNI', 'numero_documento' => '30000001',
            'fecha_nacimiento' => '1990-01-01', 'edad' => 30,
            'correo' => 'cancelado@test.net',
            'direccion' => 'x', 'ciudad' => 'x', 'telefono' => '1',
            'categoria' => (string) $this->categoria->id,
            'subtotal' => 100,
        ]);
        ParticipanteTallerSesion::create([
            'participante_id'    => $part->id,
            'sesion_congreso_id' => $this->sesionEticaManana->id,
            'taller_id'          => $this->tallerEtica->id,
            'unit_price'         => 50,
            'total'              => 50,
        ]);

        $response = $this->getJson("/api/v1/event/{$this->evento->id}")->assertOk();
        $talleres = collect($response->json('eventos.talleres'));
        $tallerEtica = $talleres->firstWhere('id', $this->tallerEtica->id);
        $sesion = collect($tallerEtica['sesiones'])->firstWhere('id', $this->sesionEticaManana->id);

        $this->assertSame(0, $sesion['ocupados'], 'la sesión cancelada no debería contar como ocupada.');
        $this->assertSame($this->sesionEticaManana->cupo, $sesion['disponibles']);
    }
}
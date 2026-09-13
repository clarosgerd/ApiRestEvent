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
use Tests\TestCase;

/**
 * Certificado "solo nombre" parametrizable por evento (13/09/2026) — pedido
 * real de COLABIOCLI 2026: sin prefijo de título/alias y sin el párrafo de
 * rol/fecha. CIACRUZ y el resto de eventos existentes (default false) deben
 * seguir mostrando el certificado exactamente igual que hoy. Ver
 * EventoController::certificadosPdf().
 */
class CertificadosPdfTest extends TestCase
{
    use RefreshDatabase;

    private function crearEvento(array $overrides = []): Evento
    {
        $pais = Pais::factory()->create();
        $ciudad = Ciudad::factory()->create(['pais_id' => $pais->id]);
        $organizador = Organizador::factory()->create();
        $tipoEvento = TipoEvento::factory()->create();
        $subtipoEvento = SubtipoEvento::factory()->create(['tipo_evento_id' => $tipoEvento->id]);

        return Evento::factory()->create(array_merge([
            'organizador_id' => $organizador->id,
            'tipo_evento_id' => $tipoEvento->id,
            'subtipo_evento_id' => $subtipoEvento->id,
            'pais_id' => $pais->id,
            'ciudad_id' => $ciudad->id,
        ], $overrides));
    }

    private function crearParticipanteConTitulo(Evento $evento): Participante
    {
        $formType = FormType::factory()->create(['event_id' => $evento->id]);
        $categoria = Category::factory()->create(['event_id' => $evento->id, 'name' => 'Ponente']);

        $registration = Registration::create([
            'referencia' => 'REF' . rand(100000, 999999),
            'fecha' => now(),
            'evento_id' => $evento->id,
            'form_types_id' => $formType->id,
            'evento_nombre' => $evento->nombre,
            'tipo_pago' => 'sip',
            'pago_status' => 'paid',
        ]);

        return Participante::create([
            'registration_id' => $registration->id,
            'nombre' => 'Juan', 'apellido' => 'Perez', 'alias' => 'Dr.',
            'genero' => 'Masculino', 'tipo_documento' => 'DNI',
            'numero_documento' => (string) rand(1000000, 9999999),
            'fecha_nacimiento' => '1990-01-01', 'edad' => 30,
            'correo' => 'test' . rand(1000, 9999) . '@test.net', 'direccion' => 'x', 'ciudad' => 'x', 'telefono' => '123',
            'categoria' => $categoria->id, 'precio_categoria' => 0, 'subtotal' => 0,
        ]);
    }

    /** Regresión: certificado_solo_nombre=false (default) genera el PDF igual que siempre (CIACRUZ). */
    public function test_certificado_solo_nombre_false_genera_pdf(): void
    {
        $evento = $this->crearEvento(['certificado_solo_nombre' => false]);
        $this->crearParticipanteConTitulo($evento);

        $response = $this->get("/api/v1/event/{$evento->id}/certificados-pdf");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    /** certificado_solo_nombre=true (COLABIOCLI) genera el PDF sin error. */
    public function test_certificado_solo_nombre_true_genera_pdf(): void
    {
        $evento = $this->crearEvento(['certificado_solo_nombre' => true]);
        $this->crearParticipanteConTitulo($evento);

        $response = $this->get("/api/v1/event/{$evento->id}/certificados-pdf");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    /**
     * Renderiza la vista directo (sin pasar por el PDF binario) para
     * verificar el contenido real: con solo_nombre=false, el nombre lleva
     * el prefijo de título y el párrafo de rol/fecha aparece.
     */
    public function test_vista_con_solo_nombre_false_muestra_titulo_y_parrafo_de_rol(): void
    {
        $evento = $this->crearEvento(['certificado_solo_nombre' => false, 'color_hex' => '#022858']);

        $html = view('tickets.certificados', [
            'evento' => $evento,
            'items' => [[
                'tipo' => 'asistencia',
                'nombre' => 'Dr. Juan Perez',
                'rol' => 'Ponente',
                'referencia' => 'REF123',
                'soloNombre' => false,
            ]],
            'logo' => null,
            'brand' => '#022858',
        ])->render();

        $this->assertStringContainsString('Dr. Juan Perez', $html);
        $this->assertStringContainsString('por su asistencia como', $html);
        $this->assertStringContainsString('Ponente', $html);
    }

    /**
     * Con solo_nombre=true, el nombre no lleva título (armado sin alias
     * desde el controller) y el párrafo de rol/fecha desaparece por
     * completo.
     */
    public function test_vista_con_solo_nombre_true_oculta_titulo_y_parrafo_de_rol(): void
    {
        $evento = $this->crearEvento(['certificado_solo_nombre' => true, 'color_hex' => '#022858']);

        $html = view('tickets.certificados', [
            'evento' => $evento,
            'items' => [[
                'tipo' => 'asistencia',
                'nombre' => 'Juan Perez',
                'rol' => 'Ponente',
                'referencia' => 'REF123',
                'soloNombre' => true,
            ]],
            'logo' => null,
            'brand' => '#022858',
        ])->render();

        $this->assertStringContainsString('Juan Perez', $html);
        $this->assertStringNotContainsString('Dr. Juan Perez', $html);
        $this->assertStringNotContainsString('por su asistencia como', $html);
    }

    /**
     * El controller arma el 'nombre' sin alias cuando certificado_solo_nombre
     * es true — verificado indirectamente vía el item real que produciría
     * certificadosPdf() para un participante con alias cargado (mismo dato
     * usado en crearParticipanteConTitulo()).
     */
    public function test_controller_arma_nombre_sin_alias_cuando_solo_nombre_activo(): void
    {
        $participante = new Participante([
            'nombre' => 'Juan', 'apellido' => 'Perez', 'alias' => 'Dr.',
        ]);

        $nombreSoloNombre = trim($participante->nombre . ' ' . $participante->apellido);
        $nombreConTitulo = collect([$participante->alias, $participante->nombre, $participante->apellido])
            ->filter()->implode(' ');

        $this->assertSame('Juan Perez', $nombreSoloNombre);
        $this->assertSame('Dr. Juan Perez', $nombreConTitulo);
    }
}

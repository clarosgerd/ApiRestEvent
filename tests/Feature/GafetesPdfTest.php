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
 * Gafetes con tamaño/layout parametrizable por evento (13/09/2026) — pedido
 * real de COLABIOCLI 2026, manteniendo CIACRUZ y el resto de eventos
 * existentes intactos. Ver EventoController::gafetesPdf()/gafeteDims().
 */
class GafetesPdfTest extends TestCase
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

    private function crearParticipante(Evento $evento): Participante
    {
        $formType = FormType::factory()->create(['event_id' => $evento->id]);
        $categoria = Category::factory()->create(['event_id' => $evento->id]);

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
            'nombre' => 'Nombre' . rand(1000, 9999), 'apellido' => 'Apellido',
            'genero' => 'Femenino', 'tipo_documento' => 'DNI',
            'numero_documento' => (string) rand(1000000, 9999999),
            'fecha_nacimiento' => '1990-01-01', 'edad' => 30,
            'correo' => 'test' . rand(1000, 9999) . '@test.net', 'direccion' => 'x', 'ciudad' => 'x', 'telefono' => '123',
            'categoria' => $categoria->id, 'precio_categoria' => 0, 'subtotal' => 0,
        ]);
    }

    /** Regresión: sin gafete_config (CIACRUZ y todo evento existente), el PDF se genera igual que siempre. */
    public function test_gafete_config_nulo_genera_pdf_con_defaults(): void
    {
        $evento = $this->crearEvento(['gafete_config' => null]);
        $this->crearParticipante($evento);

        $response = $this->get("/api/v1/event/{$evento->id}/gafetes-pdf");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    /** gafete_config custom (ej. COLABIOCLI) se genera sin error. */
    public function test_gafete_config_custom_genera_pdf(): void
    {
        $evento = $this->crearEvento([
            'gafete_config' => ['width_cm' => 8.5, 'height_cm' => 5.5, 'per_row' => 2, 'paper' => 'letter', 'orientation' => 'portrait'],
        ]);
        $this->crearParticipante($evento);

        $response = $this->get("/api/v1/event/{$evento->id}/gafetes-pdf");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    /** per_row fuera de rango se clampea (gafeteDims()) en vez de romper. */
    public function test_gafete_config_con_per_row_fuera_de_rango_no_rompe(): void
    {
        $evento = $this->crearEvento([
            'gafete_config' => ['per_row' => 0],
        ]);
        $this->crearParticipante($evento);

        $response = $this->get("/api/v1/event/{$evento->id}/gafetes-pdf");

        $response->assertOk();
    }

    /** Evento sin participantes no rompe, con cualquier config. */
    public function test_sin_participantes_no_rompe(): void
    {
        $evento = $this->crearEvento(['gafete_config' => ['width_cm' => 6]]);

        $response = $this->get("/api/v1/event/{$evento->id}/gafetes-pdf");

        $response->assertOk();
    }
}

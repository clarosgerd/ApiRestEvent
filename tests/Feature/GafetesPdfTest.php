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

    /**
     * Gafete tipo "pegatina" (23/09/2026) — solo QR, tamaño de página custom
     * (no A4/Carta). El PDF se sigue generando sin error; el tamaño exacto
     * de página no es verificable por HTTP status, se confirma por HTTP real
     * en el checklist de deploy.
     */
    public function test_gafete_tipo_label_genera_pdf(): void
    {
        $evento = $this->crearEvento([
            'gafete_config' => ['tipo' => 'label', 'width_cm' => 3, 'height_cm' => 3],
        ]);
        $this->crearParticipante($evento);

        $response = $this->get("/api/v1/event/{$evento->id}/gafetes-pdf");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    /**
     * No-regresión (24/09/2026) — bug real encontrado con datos de
     * COLABIOCLI 2026: una pegatina más ANCHA que alta (horizontal, ej.
     * 7x5cm) generaba 2 páginas en blanco antes de la real, porque el QR se
     * dimensionaba solo en base al ancho de la pegatina (ver
     * tickets/gafete-label.blade.php) — un `assertOk()` no detecta esto
     * (sigue siendo HTTP 200 con PDF válido, solo con páginas de más), así
     * que este test cuenta los objetos `/Type /Page` reales dentro del PDF.
     */
    public function test_gafete_pdf_de_un_participante_tipo_label_horizontal_da_una_sola_pagina(): void
    {
        $evento = $this->crearEvento([
            'gafete_config' => ['tipo' => 'label', 'width_cm' => 7, 'height_cm' => 5],
        ]);
        $participante = $this->crearParticipante($evento);

        $response = $this->get("/api/v1/event/{$evento->id}/participantes/{$participante->id}/gafete-pdf");

        $response->assertOk();
        $paginas = preg_match_all('/\/Type\s*\/Page[^s]/', $response->getContent());
        $this->assertSame(1, $paginas, 'El PDF de una pegatina horizontal debe tener exactamente 1 página.');
    }

    /** tipo=completo explícito se comporta exactamente igual que hoy (no-regresión). */
    public function test_gafete_tipo_completo_explicito_no_rompe(): void
    {
        $evento = $this->crearEvento([
            'gafete_config' => ['tipo' => 'completo', 'width_cm' => 7, 'height_cm' => 5],
        ]);
        $this->crearParticipante($evento);

        $response = $this->get("/api/v1/event/{$evento->id}/gafetes-pdf");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    /** Impresión por demanda (23/09/2026) — un solo participante. */
    public function test_gafete_pdf_de_un_participante(): void
    {
        $evento = $this->crearEvento();
        $participante = $this->crearParticipante($evento);

        $response = $this->get("/api/v1/event/{$evento->id}/participantes/{$participante->id}/gafete-pdf");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    /** Impresión por demanda respeta tipo=label. */
    public function test_gafete_pdf_de_un_participante_tipo_label(): void
    {
        $evento = $this->crearEvento(['gafete_config' => ['tipo' => 'label', 'width_cm' => 3, 'height_cm' => 3]]);
        $participante = $this->crearParticipante($evento);

        $response = $this->get("/api/v1/event/{$evento->id}/participantes/{$participante->id}/gafete-pdf");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    /** 404 si el participante no pertenece al evento de la URL. */
    public function test_gafete_pdf_de_un_participante_404_si_no_pertenece_al_evento(): void
    {
        $evento = $this->crearEvento();
        $otroEvento = $this->crearEvento();
        $participante = $this->crearParticipante($otroEvento);

        $response = $this->get("/api/v1/event/{$evento->id}/participantes/{$participante->id}/gafete-pdf");

        $response->assertNotFound();
    }
}

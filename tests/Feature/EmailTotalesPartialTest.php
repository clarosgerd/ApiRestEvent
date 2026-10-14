<?php

namespace Tests\Feature;

use App\Mail\PagoConfirmadoMail;
use App\Models\Category;
use App\Models\Ciudad;
use App\Models\Evento;
use App\Models\FormType;
use App\Models\Organizador;
use App\Models\Pais;
use App\Models\Registration;
use App\Models\RegistrationTotal;
use App\Models\SubtipoEvento;
use App\Models\TipoEvento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `emails/partials/totales.blade.php` — bug real encontrado por el usuario
 * (13/10/2026) a partir de un e-ticket real: la fila "Taller" del RESUMEN
 * DE PAGO leía `$totals->souvenirs` en vez de `$totals->talleres` (copy-paste
 * del mismo bug ya corregido una vez en `participantes.blade.php`, ver
 * EmailParticipantesPartialTest). Con souvenirs=0 y talleres=1, la fila
 * mostraba Bs0.00 aunque el participante sí tenía Bs1.00 en talleres — el
 * monto realmente cobrado (grand_total) nunca estuvo mal, solo esta línea
 * del desglose.
 */
class EmailTotalesPartialTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_fila_taller_muestra_el_total_de_talleres_no_el_de_souvenirs(): void
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
        $formType = FormType::factory()->create(['event_id' => $evento->id, 'tipo' => 'congreso']);
        Category::factory()->create(['event_id' => $evento->id, 'price' => 100]);

        $registration = Registration::create([
            'referencia' => 'REF' . rand(100000, 999999),
            'fecha' => now(),
            'evento_id' => $evento->id,
            'form_types_id' => $formType->id,
            'evento_nombre' => $evento->nombre,
            'tipo_pago' => 'QR',
            'pago_status' => 'paid',
        ]);

        // souvenirs=0, talleres=1 — valores distintos a propósito, para que
        // un swap de campo (el bug real) no pueda pasar inadvertido.
        RegistrationTotal::create([
            'registration_id' => $registration->id,
            'inscripcion' => 100, 'donacion' => 0, 'souvenirs' => 0, 'talleres' => 1,
            'fee' => 5, 'descuento' => 0, 'descuento_registrante' => 0, 'grand_total' => 106,
        ]);

        $registration->loadMissing([
            'participants.souvenirParticipante',
            'participants.talleresSesiones.taller',
            'totals', 'evento.organizador', 'formType',
        ]);

        $html = (new PagoConfirmadoMail($registration))->render();

        $this->assertStringContainsString('Taller</td><td style="padding:5px 0;text-align:right;">Bs1.00', $html);
        $this->assertStringNotContainsString('Taller</td><td style="padding:5px 0;text-align:right;">Bs0.00', $html);
    }
}

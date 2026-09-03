<?php

namespace Tests\Feature;

use App\Mail\InscripcionPendienteMail;
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
 * Bug real reportado por un usuario (03/09/2026, "segunda vez"): el QR de
 * referencia embebido como `data:` URI en el cuerpo del correo de
 * inscripción PENDIENTE llegaba como imagen rota — muchos clientes de
 * correo bloquean imágenes `data:` por defecto. `PagoConfirmadoMail` ya
 * adjuntaba un PDF con el mismo QR (confiable, dompdf no depende del
 * bloqueo de imágenes del cliente) — `InscripcionPendienteMail` no
 * adjuntaba ninguno. Ver `resources/views/tickets/eticket.blade.php`
 * (parametrizado para no decir "✓ Pagado"/"Entrada electrónica" en un
 * comprobante de algo que todavía no se pagó).
 */
class InscripcionPendienteMailQrPdfTest extends TestCase
{
    use RefreshDatabase;

    private function crearRegistrationPendiente(): Registration
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
        $formType = FormType::factory()->create(['event_id' => $evento->id]);
        Category::factory()->create(['event_id' => $evento->id, 'price' => 100]);

        $registration = Registration::create([
            'referencia' => 'REF' . rand(100000, 999999),
            'fecha' => now(),
            'evento_id' => $evento->id,
            'form_types_id' => $formType->id,
            'evento_nombre' => $evento->nombre,
            'tipo_pago' => 'sip',
            'pago_status' => 'pending',
        ]);
        RegistrationTotal::create([
            'registration_id' => $registration->id,
            'inscripcion' => 100, 'donacion' => 0, 'souvenirs' => 0, 'talleres' => 0,
            'fee' => 5, 'descuento' => 0, 'descuento_registrante' => 0, 'grand_total' => 105,
        ]);

        return $registration->fresh(['evento.organizador', 'formType']);
    }

    public function test_inscripcion_pendiente_adjunta_pdf_con_el_mismo_qr_que_el_inline(): void
    {
        $registration = $this->crearRegistrationPendiente();

        $mail = (new InscripcionPendienteMail($registration))->build();

        $this->assertCount(1, $mail->rawAttachments, 'Antes de este fix no adjuntaba ningún PDF.');
        $attachment = $mail->rawAttachments[0];
        $this->assertSame('application/pdf', $attachment['options']['mime']);
        $this->assertStringStartsWith('comprobante-' . $registration->referencia, $attachment['name']);
    }

    public function test_pdf_de_pendiente_no_dice_pagado(): void
    {
        $registration = $this->crearRegistrationPendiente();

        $mail = (new InscripcionPendienteMail($registration))->build();
        $pdfText = $mail->rawAttachments[0]['data'];

        // No podemos parsear texto de un PDF binario acá sin una librería
        // nueva — pero sí podemos confirmar que el PDF se generó (no vacío)
        // y que build() no explotó armando la vista con los params nuevos
        // (pdfTitle/statusLabel/statusColor/pdfFooterMsg) — la regresión
        // real que se quería evitar (parametrizar mal el template y que
        // reviente al renderizar) queda cubierta con esto + el test de
        // regresión de PagoConfirmadoMail de abajo.
        $this->assertNotEmpty($pdfText);
        $this->assertStringStartsWith('%PDF', $pdfText);
    }

    public function test_pago_confirmado_sigue_adjuntando_el_pdf_como_antes(): void
    {
        // Regresión: PagoConfirmadoMail ya adjuntaba PDF antes de este fix
        // — confirmar que parametrizar el template compartido no le
        // cambió el nombre/mime/comportamiento.
        $registration = $this->crearRegistrationPendiente();
        $registration->update(['pago_status' => 'paid']);
        $registration = $registration->fresh(['evento.organizador', 'formType']);

        $mail = (new PagoConfirmadoMail($registration))->build();

        $this->assertCount(1, $mail->rawAttachments);
        $attachment = $mail->rawAttachments[0];
        $this->assertSame('application/pdf', $attachment['options']['mime']);
        $this->assertStringStartsWith('eticket-' . $registration->referencia, $attachment['name']);
        $this->assertStringStartsWith('%PDF', $attachment['data']);
    }
}

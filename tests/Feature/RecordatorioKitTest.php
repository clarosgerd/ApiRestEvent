<?php

namespace Tests\Feature;

use App\Mail\RecordatorioKitMail;
use App\Models\Category;
use App\Models\Ciudad;
use App\Models\Evento;
use App\Models\FormType;
use App\Models\Organizador;
use App\Models\Pais;
use App\Models\Participante;
use App\Models\Registration;
use App\Models\Souvenir;
use App\Models\SouvenirParticipante;
use App\Models\SubtipoEvento;
use App\Models\TipoEvento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Recordatorio de recojo de kit (04/10/2026): solo a inscripciones con kit
 * (souvenir o número de corredor). Antes salía a todo registro pagado.
 */
class RecordatorioKitTest extends TestCase
{
    use RefreshDatabase;

    private Evento $evento;

    private FormType $formType;

    protected function setUp(): void
    {
        parent::setUp();

        $pais = Pais::factory()->create();
        $ciudad = Ciudad::factory()->create(['pais_id' => $pais->id]);
        $organizador = Organizador::factory()->create();
        $tipoEvento = TipoEvento::factory()->create();
        $subtipoEvento = SubtipoEvento::factory()->create(['tipo_evento_id' => $tipoEvento->id]);

        $this->evento = Evento::factory()->create([
            'organizador_id' => $organizador->id, 'tipo_evento_id' => $tipoEvento->id,
            'subtipo_evento_id' => $subtipoEvento->id, 'pais_id' => $pais->id, 'ciudad_id' => $ciudad->id,
            'fecha_inicio' => now()->addDays(2)->toDateString(),
        ]);
        $this->formType = FormType::factory()->create(['event_id' => $this->evento->id, 'requiere_categoria' => false]);
    }

    private function inscripcionPagada(array $participanteOverrides = []): Participante
    {
        $registration = Registration::factory()->create([
            'evento_id' => $this->evento->id, 'form_types_id' => $this->formType->id,
            'referencia' => 'LA-KIT-' . uniqid(), 'fecha' => now(), 'evento_nombre' => $this->evento->nombre,
            'tipo_pago' => 'EFECTIVO', 'pago_status' => 'paid',
        ]);

        return Participante::create(array_merge([
            'registration_id' => $registration->id,
            'nombre' => 'Ana', 'apellido' => 'Prueba', 'genero' => 'Femenino',
            'tipo_documento' => 'DNI', 'numero_documento' => (string) rand(10000000, 99999999),
            'fecha_nacimiento' => '1990-01-01', 'edad' => 34, 'correo' => 'ana' . rand(1, 99999) . '@test.net',
            'direccion' => 'x', 'ciudad' => 'x', 'telefono' => '123',
            'categoria' => 'General', 'subtotal' => 100,
        ], $participanteOverrides));
    }

    public function test_no_envia_recordatorio_a_inscripcion_sin_kit(): void
    {
        Mail::fake();
        $this->inscripcionPagada();

        $this->artisan('notificaciones:recordatorio-kit')->assertSuccessful();

        Mail::assertNotSent(RecordatorioKitMail::class);
    }

    public function test_envia_recordatorio_a_inscripcion_con_souvenir(): void
    {
        Mail::fake();
        $participante = $this->inscripcionPagada();
        $souvenir = Souvenir::factory()->create(['form_types_id' => $this->formType->id]);
        SouvenirParticipante::create([
            'participante_id' => $participante->id, 'souvenir_id' => $souvenir->id,
            'nombre' => $souvenir->name, 'precio' => 0,
        ]);

        $this->artisan('notificaciones:recordatorio-kit')->assertSuccessful();

        Mail::assertSent(RecordatorioKitMail::class, 1);
    }

    public function test_envia_recordatorio_a_inscripcion_con_numero_de_corredor(): void
    {
        Mail::fake();
        $this->inscripcionPagada(['numero_corredor' => '123']);

        $this->artisan('notificaciones:recordatorio-kit')->assertSuccessful();

        Mail::assertSent(RecordatorioKitMail::class, 1);
    }
}

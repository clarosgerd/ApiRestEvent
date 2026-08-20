<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Evento;
use App\Models\FormType;
use App\Models\Participante;
use App\Models\SesionCongreso;
use App\Models\Taller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Bug real encontrado el 19/08/2026: `StoreRegistrationRequest` nunca tuvo
 * una regla de validación para `participantes.*.talleres` — Laravel
 * descarta en silencio cualquier clave del payload sin regla al llamar
 * `$request->validated()`, así que toda inscripción con taller vía la API
 * real llegaba a `CrearInscripcionAction` con `talleres: []`, sin importar
 * lo que mandara el frontend. Nunca se creaba la fila en
 * `participante_taller_sesion`.
 *
 * `TallerSeleccionInscripcionTest` (las reglas de duplicado/solape/
 * capacidad/requeridos) no lo agarró porque llama a
 * `CrearInscripcionAction::handle()` directo, saltándose esta capa de
 * validación HTTP — por eso este test aparte, específicamente vía
 * `postJson('/api/v1/registrations', ...)` como lo hace el frontend real.
 */
class TallerRegistroHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_taller_seleccionado_se_persiste_via_endpoint_real_de_registro(): void
    {
        $this->actingAsPersona();

        $evento = Evento::factory()->create(['talleres_con_costo' => true])->fresh();
        $formType = FormType::factory()->create([
            'event_id' => $evento->id,
            'requiere_categoria' => true,
        ]);
        $categoria = Category::factory()->create([
            'event_id' => $evento->id,
            'price' => 100,
        ]);
        $taller = Taller::factory()->create([
            'evento_id' => $evento->id,
            'modalidad' => 'OPTIONAL',
            'precio' => 50,
        ]);
        $sesion = SesionCongreso::factory()->create([
            'evento_id' => $evento->id,
            'taller_id' => $taller->id,
            'fecha' => '2026-09-18',
            'hora_inicio' => '09:00:00',
            'hora_fin' => '11:00:00',
            'cupo' => 30,
        ]);

        $reference = 'REF-TALLER-' . Str::random(8);
        $payload = [[
            'referencia' => $reference,
            'fecha' => now()->toDateTimeString(),
            'evento_id' => $evento->id,
            'form_types_id' => $formType->id,
            'evento_nombre' => $evento->nombre,
            'tipo_pago' => 'QR',
            'pago_status' => 'pending',
            'totales' => [
                'inscripcion' => 100,
                'donacion' => 0,
                'souvenirs' => 0,
                'talleres' => 50,
                'fee' => round((100 + 50) * (float) $evento->fee_pct, 2),
                'descuento' => 0,
                'grand_total' => 100 + 50 + round((100 + 50) * (float) $evento->fee_pct, 2),
            ],
            'participantes' => [[
                'nombre' => 'Ana',
                'apellido' => 'Prueba',
                'alias' => 'ana',
                'genero' => 'Femenino',
                'tipoDocumento' => 'CI',
                'numeroDocumento' => '87654321',
                'polera' => 'No shirt',
                'precioPolera' => 0,
                'nacimiento' => ['dia' => 10, 'mes' => 5, 'anio' => 1995],
                'edad' => 31,
                'correo' => 'ana@example.com',
                'direccion' => 'x',
                'ciudad' => 'x',
                'telefono' => '22001122',
                'categoria' => $categoria->id,
                'precioCategoria' => 100,
                'donacion' => 0,
                'promoDescuento' => 0,
                'promoCodigo' => '',
                'subtotal' => 150,
                'contacto_emergencia' => [
                    'nombre' => 'Luis', 'celular' => '099111111', 'relacion' => 'Padre',
                ],
                'souvenirs' => [],
                'talleres' => [[
                    'taller_id' => $taller->id,
                    'sesion_congreso_id' => $sesion->id,
                    'taller_nombre' => $taller->nombre,
                    'unit_price' => 50,
                ]],
            ]],
        ]];

        $this->postJson('/api/v1/registrations', $payload)->assertCreated();

        $participante = Participante::where('numero_documento', '87654321')->firstOrFail();
        $this->assertDatabaseHas('participante_taller_sesion', [
            'participante_id' => $participante->id,
            'sesion_congreso_id' => $sesion->id,
            'taller_id' => $taller->id,
        ]);
    }
}

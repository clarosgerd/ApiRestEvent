<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ciudad;
use App\Models\Evento;
use App\Models\FormType;
use App\Models\Genero;
use App\Models\NumeracionRango;
use App\Models\Organizador;
use App\Models\Pais;
use App\Models\Participante;
use App\Models\Registration;
use App\Models\SubtipoEvento;
use App\Models\TipoEvento;
use App\Support\NumeracionRangoChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Aviso de numeración vs. género/edad real (16/09/2026) — no tenía tests
 * dedicados hasta ahora (solo cobertura indirecta vía la columna
 * AlertaNumeracion del CSV). Se agrega acá el caso nuevo del 23/09/2026:
 * numero_min/numero_max ahora son opcionales (recategorización visual),
 * un rango sin ellos no debe generar ninguna alerta.
 */
class NumeracionRangoCheckerTest extends TestCase
{
    use RefreshDatabase;

    public function test_rango_matcheado_sin_numero_min_max_no_genera_alerta(): void
    {
        $pais = Pais::factory()->create();
        $ciudad = Ciudad::factory()->create(['pais_id' => $pais->id]);
        $organizador = Organizador::factory()->create();
        $tipoEvento = TipoEvento::factory()->create();
        $subtipoEvento = SubtipoEvento::factory()->create(['tipo_evento_id' => $tipoEvento->id]);
        $evento = Evento::factory()->create([
            'organizador_id' => $organizador->id, 'tipo_evento_id' => $tipoEvento->id,
            'subtipo_evento_id' => $subtipoEvento->id, 'pais_id' => $pais->id, 'ciudad_id' => $ciudad->id,
        ]);
        $formType = FormType::factory()->create(['event_id' => $evento->id]);
        $categoria = Category::factory()->create(['event_id' => $evento->id, 'name' => '5K']);
        $femenino = Genero::where('nombre', 'Femenino')->first();

        // Rango cargado SOLO para recategorización (sin numero_min/max) —
        // caso nuevo desde que dejaron de ser obligatorios.
        NumeracionRango::create([
            'category_id' => $categoria->id, 'genero_id' => $femenino->id,
            'edad_min' => 25, 'edad_max' => 35, 'color' => '#abcdef',
        ]);

        $registration = Registration::create([
            'referencia' => 'REF' . rand(100000, 999999), 'fecha' => now(),
            'evento_id' => $evento->id, 'form_types_id' => $formType->id,
            'evento_nombre' => $evento->nombre, 'tipo_pago' => 'EFECTIVO', 'pago_status' => 'paid',
        ]);
        $participante = Participante::create([
            'registration_id' => $registration->id,
            'nombre' => 'Ana', 'apellido' => 'Prueba', 'genero' => 'Femenino',
            'tipo_documento' => 'DNI', 'numero_documento' => '12345',
            'fecha_nacimiento' => now()->subYears(30)->toDateString(), 'edad' => 30,
            'correo' => 'ana@test.net', 'direccion' => 'x', 'ciudad' => 'x', 'telefono' => '123',
            'categoria' => (string) $categoria->id,
            'precio_categoria' => 50, 'subtotal' => 50,
            'numero_corredor' => '9999', // cualquier bib — no hay rango de numeración contra qué comparar.
        ]);

        $this->assertNull(NumeracionRangoChecker::alertaPara($participante, $evento));
    }
}

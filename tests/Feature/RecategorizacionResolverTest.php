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
use App\Support\RecategorizacionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Recategorización visual por edad/género (23/09/2026) — a diferencia de
 * NumeracionRangoChecker (que solo avisa dentro de la propia categoría),
 * busca en TODAS las categorías del evento. Ver plan del 23/09/2026 y
 * memoria del proyecto.
 */
class RecategorizacionResolverTest extends TestCase
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
            'organizador_id' => $organizador->id,
            'tipo_evento_id' => $tipoEvento->id,
            'subtipo_evento_id' => $subtipoEvento->id,
            'pais_id' => $pais->id,
            'ciudad_id' => $ciudad->id,
        ]);

        $this->formType = FormType::factory()->create(['event_id' => $this->evento->id]);
    }

    private function crearParticipante(int $categoriaId, string $genero, string $fechaNacimiento, ?int $edad = null): Participante
    {
        $registration = Registration::create([
            'referencia' => 'REF' . rand(100000, 999999),
            'fecha' => now(),
            'evento_id' => $this->evento->id,
            'form_types_id' => $this->formType->id,
            'evento_nombre' => $this->evento->nombre,
            'tipo_pago' => 'EFECTIVO',
            'pago_status' => 'paid',
        ]);

        return Participante::create([
            'registration_id' => $registration->id,
            'nombre' => 'Ana', 'apellido' => 'Prueba', 'genero' => $genero,
            'tipo_documento' => 'DNI', 'numero_documento' => (string) rand(1000000, 9999999),
            'fecha_nacimiento' => $fechaNacimiento, 'edad' => $edad ?? (int) abs(round(\Carbon\Carbon::parse($fechaNacimiento)->diffInYears(now()))),
            'correo' => 'a' . rand(1, 999999) . '@test.net', 'direccion' => 'x', 'ciudad' => 'x', 'telefono' => '123',
            'categoria' => (string) $categoriaId,
            'precio_categoria' => 50, 'subtotal' => 50,
        ]);
    }

    public function test_matchea_una_categoria_distinta_a_la_propia(): void
    {
        $femenino = Genero::where('nombre', 'Femenino')->first();
        $categoria5k = Category::factory()->create(['event_id' => $this->evento->id, 'name' => '5K']);
        $categoria10k = Category::factory()->create(['event_id' => $this->evento->id, 'name' => '10K']);

        // El rango que realmente le corresponde está en 10K, no en la 5K que eligió.
        NumeracionRango::create([
            'category_id' => $categoria10k->id, 'genero_id' => $femenino->id,
            'edad_min' => 30, 'edad_max' => 39, 'color' => '#ff0000',
        ]);

        $participante = $this->crearParticipante($categoria5k->id, 'Femenino', now()->subYears(35)->toDateString());

        $resultado = RecategorizacionResolver::paraParticipante($participante, $this->evento);

        $this->assertNotNull($resultado);
        $this->assertSame('10K', $resultado['category']->name);
        $this->assertSame('#ff0000', $resultado['color']);
    }

    public function test_sin_ningun_rango_que_matchee_devuelve_null(): void
    {
        $femenino = Genero::where('nombre', 'Femenino')->first();
        $categoria5k = Category::factory()->create(['event_id' => $this->evento->id, 'name' => '5K']);

        NumeracionRango::create([
            'category_id' => $categoria5k->id, 'genero_id' => $femenino->id,
            'edad_min' => 18, 'edad_max' => 29, 'color' => '#00ff00',
        ]);

        // 35 años, no cae en el rango 18-29 configurado.
        $participante = $this->crearParticipante($categoria5k->id, 'Femenino', now()->subYears(35)->toDateString());

        $this->assertNull(RecategorizacionResolver::paraParticipante($participante, $this->evento));
    }

    public function test_participante_sin_categoria_propia_resoluble_usa_edad_cruda(): void
    {
        $masculino = Genero::where('nombre', 'Masculino')->first();
        $categoriaReal = Category::factory()->create(['event_id' => $this->evento->id, 'name' => 'Elite']);

        NumeracionRango::create([
            'category_id' => $categoriaReal->id, 'genero_id' => $masculino->id,
            'edad_min' => 18, 'edad_max' => 99, 'color' => '#0000ff',
        ]);

        // categoria=99999 no existe como Category real (ej. texto libre de un sync externo).
        $registration = Registration::create([
            'referencia' => 'REF' . rand(100000, 999999), 'fecha' => now(),
            'evento_id' => $this->evento->id, 'form_types_id' => $this->formType->id,
            'evento_nombre' => $this->evento->nombre, 'tipo_pago' => 'externo', 'pago_status' => 'paid',
        ]);
        $participante = Participante::create([
            'registration_id' => $registration->id,
            'nombre' => 'Juan', 'apellido' => 'Prueba', 'genero' => 'Masculino',
            'tipo_documento' => 'DNI', 'numero_documento' => '999888',
            'fecha_nacimiento' => '1900-01-01', 'edad' => 40,
            'correo' => 'juan@test.net', 'direccion' => 'x', 'ciudad' => 'x', 'telefono' => '123',
            'categoria' => 'Texto Libre Sin Match',
            'precio_categoria' => 0, 'subtotal' => 0,
        ]);

        $resultado = RecategorizacionResolver::paraParticipante($participante, $this->evento);

        $this->assertNotNull($resultado);
        $this->assertSame('Elite', $resultado['category']->name);
    }

}

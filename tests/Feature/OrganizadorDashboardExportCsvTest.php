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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Campos de carrera/congreso en el CSV de participantes al cliente
 * (18/09/2026) — ver análisis en la memoria del proyecto
 * (project_reportes_csv_campos_carrera_congreso). Criterio por presencia de
 * datos: numeración/chip solo si el evento usa numeración (tipo de evento,
 * mismo flag ya usado para `UsaNumeracion`), curso pre-congreso solo si el
 * evento realmente tiene esos datos — ambos independientes entre sí.
 */
class OrganizadorDashboardExportCsvTest extends TestCase
{
    use RefreshDatabase;

    private function crearEvento(?string $tipoEventoNombre = null): Evento
    {
        $pais = Pais::factory()->create();
        $ciudad = Ciudad::factory()->create(['pais_id' => $pais->id]);
        $organizador = Organizador::factory()->create();
        $tipoEvento = TipoEvento::factory()->create(
            $tipoEventoNombre !== null ? ['nombre' => $tipoEventoNombre] : []
        );
        $subtipoEvento = SubtipoEvento::factory()->create(['tipo_evento_id' => $tipoEvento->id]);

        return Evento::factory()->create([
            'organizador_id' => $organizador->id,
            'tipo_evento_id' => $tipoEvento->id,
            'subtipo_evento_id' => $subtipoEvento->id,
            'pais_id' => $pais->id,
            'ciudad_id' => $ciudad->id,
        ]);
    }

    private function crearInscripcion(Evento $evento, FormType $formType, Category $categoria, array $overrides = []): Participante
    {
        $registration = Registration::create([
            'referencia' => 'REF' . rand(100000, 999999),
            'fecha' => now(),
            'evento_id' => $evento->id,
            'form_types_id' => $formType->id,
            'evento_nombre' => $evento->nombre,
            'tipo_pago' => 'EFECTIVO',
            'pago_status' => 'paid',
        ]);

        return Participante::create(array_merge([
            'registration_id' => $registration->id,
            'nombre' => 'Ana', 'apellido' => 'Prueba', 'genero' => 'Femenino',
            'tipo_documento' => 'DNI', 'numero_documento' => (string) rand(1000000, 9999999),
            'fecha_nacimiento' => '1995-01-01', 'edad' => 30,
            'correo' => 'ana' . rand(1, 999999) . '@test.net', 'direccion' => 'x', 'ciudad' => 'x', 'telefono' => '123',
            'categoria' => $categoria->id,
            'precio_categoria' => 50, 'subtotal' => 50,
        ], $overrides));
    }

    private function csv(Evento $evento): array
    {
        $url = URL::signedRoute('organizador.dashboard.export', ['evento' => $evento->id]);

        return array_map('str_getcsv', explode("\n", trim($this->get($url)->assertOk()->streamedContent())));
    }

    private function header(Evento $evento): array
    {
        return $this->csv($evento)[0];
    }

    public function test_evento_carrera_sin_curso_no_trae_columnas_de_curso(): void
    {
        $evento = $this->crearEvento(); // TipoEvento::factory() default = nombre random, no es "Congreso / No aplica"
        $formType = FormType::factory()->create(['event_id' => $evento->id]);
        $categoria = Category::factory()->create(['event_id' => $evento->id, 'name' => '5K', 'price' => 50]);
        $this->crearInscripcion($evento, $formType, $categoria, ['numero_corredor' => '101']);

        $header = $this->header($evento);

        $this->assertContains('NumeroCorredor', $header);
        $this->assertContains('Chip', $header);
        $this->assertContains('ActualizarNumeracionUrl', $header);
        $this->assertContains('AlertaNumeracion', $header);
        $this->assertContains('UsaNumeracion', $header);
        $this->assertNotContains('NombreCurso', $header);
        $this->assertNotContains('IdCurso', $header);
    }

    public function test_evento_congreso_sin_numeracion_no_trae_columnas_de_numeracion(): void
    {
        $evento = $this->crearEvento('Congreso / No aplica');
        $formType = FormType::factory()->create(['event_id' => $evento->id]);
        $categoria = Category::factory()->create(['event_id' => $evento->id]);
        $this->crearInscripcion($evento, $formType, $categoria);

        $header = $this->header($evento);

        $this->assertNotContains('NumeroCorredor', $header);
        $this->assertNotContains('Chip', $header);
        $this->assertNotContains('ActualizarNumeracionUrl', $header);
        $this->assertNotContains('AlertaNumeracion', $header);
        // UsaNumeracion se mantiene SIEMPRE (delivery ya depende de que exista).
        $this->assertContains('UsaNumeracion', $header);
    }

    public function test_evento_congreso_con_curso_pre_congreso_trae_columnas_de_curso(): void
    {
        $evento = $this->crearEvento('Congreso / No aplica');
        $congresista = FormType::factory()->create(['event_id' => $evento->id, 'name' => 'Congresista']);
        $cursoPreCongreso = FormType::factory()->create(['event_id' => $evento->id, 'name' => 'Curso Pre-Congreso']);
        $categoria = Category::factory()->create(['event_id' => $evento->id]);

        // Misma persona en las 2 inscripciones — mismo criterio de fusión
        // que ya usa exportCsv() para COLABIOCLI.
        $this->crearInscripcion($evento, $congresista, $categoria, ['numero_documento' => '55554444']);
        $this->crearInscripcion($evento, $cursoPreCongreso, $categoria, ['numero_documento' => '55554444']);

        $header = $this->header($evento);

        $this->assertContains('NombreCurso', $header);
        $this->assertContains('IdCurso', $header);
        // Sigue siendo congreso — sin columnas de numeración.
        $this->assertNotContains('NumeroCorredor', $header);
    }

    public function test_evento_con_numeracion_y_curso_trae_las_6_columnas(): void
    {
        // Caso híbrido a propósito (poco común en la práctica, pero real:
        // los 2 criterios son independientes entre sí) — carrera (no
        // "Congreso / No aplica") que además tiene datos de curso.
        $evento = $this->crearEvento();
        $congresista = FormType::factory()->create(['event_id' => $evento->id, 'name' => 'Congresista']);
        $cursoPreCongreso = FormType::factory()->create(['event_id' => $evento->id, 'name' => 'Curso Pre-Congreso']);
        $categoria = Category::factory()->create(['event_id' => $evento->id]);

        $this->crearInscripcion($evento, $congresista, $categoria, ['numero_documento' => '77778888', 'numero_corredor' => '202']);
        $this->crearInscripcion($evento, $cursoPreCongreso, $categoria, ['numero_documento' => '77778888']);

        $header = $this->header($evento);

        foreach (['NumeroCorredor', 'Chip', 'ActualizarNumeracionUrl', 'AlertaNumeracion', 'NombreCurso', 'IdCurso'] as $col) {
            $this->assertContains($col, $header, "Falta la columna {$col}");
        }
    }

    /**
     * Recategorización visual por edad/género (23/09/2026) — ver
     * plan/memoria del proyecto. Columnas nuevas solo si el evento tiene
     * algún NumeracionRango configurado (presencia de datos).
     */
    public function test_sin_numeracion_rango_configurado_no_trae_columnas_de_recategorizacion(): void
    {
        $evento = $this->crearEvento();
        $formType = FormType::factory()->create(['event_id' => $evento->id]);
        $categoria = Category::factory()->create(['event_id' => $evento->id, 'name' => '5K']);
        $this->crearInscripcion($evento, $formType, $categoria);

        $header = $this->header($evento);

        $this->assertNotContains('CategoriaRecalculada', $header);
        $this->assertNotContains('CategoriaRecalculadaColor', $header);
    }

    public function test_con_numeracion_rango_configurado_trae_la_categoria_recalculada(): void
    {
        $evento = $this->crearEvento();
        $formType = FormType::factory()->create(['event_id' => $evento->id]);
        $categoria5k = Category::factory()->create(['event_id' => $evento->id, 'name' => '5K']);
        $categoria10k = Category::factory()->create(['event_id' => $evento->id, 'name' => '10K']);
        $femenino = Genero::where('nombre', 'Femenino')->first();

        // El rango real (por edad/género) está en 10K, la participante eligió 5K.
        NumeracionRango::create([
            'category_id' => $categoria10k->id, 'genero_id' => $femenino->id,
            'edad_min' => 25, 'edad_max' => 35, 'color' => '#abcdef',
        ]);

        $this->crearInscripcion($evento, $formType, $categoria5k, [
            'genero' => 'Femenino', 'fecha_nacimiento' => now()->subYears(30)->toDateString(), 'edad' => 30,
        ]);

        $rows = $this->csv($evento);
        $header = $rows[0];
        $fila = array_combine($header, $rows[1]);

        $this->assertSame('10K', $fila['CategoriaRecalculada']);
        $this->assertSame('#abcdef', $fila['CategoriaRecalculadaColor']);
        // La columna original de categoría sigue mostrando lo que eligió.
        $this->assertSame('5K', $fila['Categoría']);
    }
}

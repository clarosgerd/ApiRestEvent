<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ciudad;
use App\Models\Evento;
use App\Models\FormType;
use App\Models\Organizador;
use App\Models\Pais;
use App\Models\Participante;
use App\Models\SubtipoEvento;
use App\Models\TipoEvento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * POST /event/{event}/registro-manual/bulk — RegistrationController::importarBulk
 * (carga masiva de inscripciones desde admin-eventos, solo super_admin, ver
 * brain/PLAN-REGISTRO-MANUAL-CSV-05082026.md).
 *
 * Bug real de QA (10/08/2026): guardaba `$category->name` en
 * `participantes.categoria` en vez del ID. El registro online
 * (CrearInscripcionAction) siempre guardó el ID — con las dos
 * representaciones mezcladas, el filtro por categoría de
 * Numeración/Participantes (que compara por ID) solo encontraba a los
 * participantes cargados por el otro camino. Ver
 * database/migrations/2026_08_10_150000_backfill_participante_categoria_legacy_names_to_id.php
 * para el backfill de los datos ya existentes.
 */
class RegistroManualBulkTest extends TestCase
{
    use RefreshDatabase;

    private Evento $evento;

    private Category $categoria;

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
        $this->categoria = Category::factory()->create(['event_id' => $this->evento->id, 'name' => '5K', 'price' => 50]);
        $this->formType = FormType::factory()->create(['event_id' => $this->evento->id, 'has_team' => false]);
    }

    private function participanteRow(array $overrides = []): array
    {
        return array_merge([
            'numero_documento' => '12345678',
            'tipo_documento' => 'DNI',
            'nombre' => 'Ana',
            'apellido' => 'Prueba',
            'alias' => '',
            'genero' => 'Femenino',
            'fecha_nacimiento' => '1995-06-15',
            'email' => 'ana@example.net',
            'direccion' => 'Calle Falsa 123',
            'ciudad' => 'La Paz',
            'telefono' => '77712345',
            'contacto_emergencia_nombre' => 'Juan Prueba',
            'contacto_emergencia_telefono' => '77798765',
            'contacto_emergencia_relacion' => 'Padre',
        ], $overrides);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'form_types_id' => $this->formType->id,
            'categoria' => '5K',
            'participantes' => [$this->participanteRow()],
        ], $overrides);
    }

    public function test_guarda_el_id_de_la_categoria_no_el_nombre(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $response = $this->postJson("/api/v1/event/{$this->evento->id}/registro-manual/bulk", $this->payload());

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertCount(1, $response->json('creados'));

        $participante = Participante::first();
        $this->assertSame((string) $this->categoria->id, $participante->categoria);
    }

    public function test_matchea_la_categoria_sin_distinguir_mayusculas(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $response = $this->postJson("/api/v1/event/{$this->evento->id}/registro-manual/bulk", $this->payload(['categoria' => '5k']));

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertSame((string) $this->categoria->id, Participante::first()->categoria);
    }

    public function test_categoria_inexistente_devuelve_422(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $response = $this->postJson("/api/v1/event/{$this->evento->id}/registro-manual/bulk", $this->payload(['categoria' => 'NoExiste']));

        $response->assertStatus(422)->assertJson(['success' => false]);
    }

    public function test_requiere_rol_super_admin(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'admin']);

        $this->postJson("/api/v1/event/{$this->evento->id}/registro-manual/bulk", $this->payload())
            ->assertStatus(403);
    }
}

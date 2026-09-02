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
        // requiere_categoria pinneado a true (FormTypeFactory lo randomiza
        // con faker->boolean()) — este endpoint elige la categoría por
        // nombre, no soporta form_types sin categoría (ver
        // PRD-precios-periodos-fechas.md, sección 0).
        $this->formType = FormType::factory()->create(['event_id' => $this->evento->id, 'has_team' => false, 'requiere_categoria' => true]);
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

    /**
     * Bug real (01/09/2026): esta carga masiva (05/08/2026) se había
     * quedado con direccion/ciudad/telefono `required`, sin actualizar
     * cuando esos campos pasaron a opcionales el 31/08/2026 — un CSV real
     * de un usuario, con la mayoría de las filas sin dirección, rechazaba
     * el archivo entero con 422 antes de crear una sola inscripción.
     */
    public function test_acepta_direccion_ciudad_telefono_vacios(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $response = $this->postJson("/api/v1/event/{$this->evento->id}/registro-manual/bulk", $this->payload([
            'participantes' => [$this->participanteRow(['direccion' => '', 'ciudad' => '', 'telefono' => ''])],
        ]));

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertCount(1, $response->json('creados'));
    }

    public function test_categoria_inexistente_devuelve_422(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $response = $this->postJson("/api/v1/event/{$this->evento->id}/registro-manual/bulk", $this->payload(['categoria' => 'NoExiste']));

        $response->assertStatus(422)->assertJson(['success' => false]);
    }

    /**
     * Categorías por form_type (27/08/2026) — ver
     * PLAN-CATEGORIAS-POR-FORM-TYPE-27082026.md. Una categoría con
     * `formulario_id` cargado para OTRO form_type no debe resolverse acá
     * aunque el nombre coincida.
     */
    public function test_categoria_de_otro_form_type_devuelve_422(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $otroFormType = FormType::factory()->create(['event_id' => $this->evento->id]);
        $this->categoria->update(['formulario_id' => $otroFormType->id]);

        $response = $this->postJson("/api/v1/event/{$this->evento->id}/registro-manual/bulk", $this->payload());

        $response->assertStatus(422)->assertJson(['success' => false]);
        $this->assertSame(0, Participante::count());
    }

    public function test_requiere_rol_super_admin(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'admin']);

        $this->postJson("/api/v1/event/{$this->evento->id}/registro-manual/bulk", $this->payload())
            ->assertStatus(403);
    }

    // ── Talleres (21/08/2026) — ver
    // brain/api_rest_event/PLAN-CARGA-MASIVA-TALLERES-21082026.md. Se
    // seleccionan por NOMBRE de taller (case-insensitive, uno o más
    // separados por ';'), igual criterio que la categoría — y cada
    // nombre tiene que resolver a un taller con exactamente una sesión
    // activa (el CSV no puede elegir entre varios horarios). ──

    public function test_sin_talleres_sigue_funcionando_igual_que_antes(): void
    {
        // El evento SÍ tiene un taller cargado (solo OPTIONAL), pero el
        // participante no lo elige — confirma que "0 talleres" sigue
        // siendo válido cuando ninguno es obligatorio.
        \App\Models\Taller::factory()->create(['evento_id' => $this->evento->id, 'modalidad' => 'OPTIONAL']);
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $response = $this->postJson("/api/v1/event/{$this->evento->id}/registro-manual/bulk", $this->payload());

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertCount(1, $response->json('creados'));
        $this->assertCount(0, $response->json('errores'));
        $this->assertDatabaseCount('participante_taller_sesion', 0);
    }

    public function test_participante_se_inscribe_a_un_taller_por_nombre(): void
    {
        // talleres_con_costo=true — sin esto ResolverPrecioTallerData
        // siempre devuelve 0 (talleres seleccionables pero gratis),
        // comportamiento correcto pero no lo que este test quiere probar.
        $this->evento->update(['talleres_con_costo' => true]);
        $taller = \App\Models\Taller::factory()->create([
            'evento_id' => $this->evento->id, 'nombre' => 'POCUS', 'modalidad' => 'OPTIONAL', 'precio' => 100,
        ]);
        $sesion = \App\Models\SesionCongreso::factory()->create([
            'evento_id' => $this->evento->id, 'taller_id' => $taller->id, 'activa' => true,
        ]);
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $response = $this->postJson("/api/v1/event/{$this->evento->id}/registro-manual/bulk",
            $this->payload(['participantes' => [$this->participanteRow(['talleres' => 'POCUS'])]])
        );

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertCount(1, $response->json('creados'));
        $participante = Participante::first();
        $this->assertDatabaseHas('participante_taller_sesion', [
            'participante_id' => $participante->id, 'taller_id' => $taller->id, 'sesion_congreso_id' => $sesion->id,
        ]);
        // categoría (50) + taller (100) + 5% de fee sobre (50+100)=150
        // (fee_incluye_talleres default TRUE en la BD) = 50+100+7.5 = 157.50.
        $total = \App\Models\RegistrationTotal::where('registration_id', $participante->registration_id)->first();
        $this->assertEquals(100.0, (float) $total->talleres);
        $this->assertEquals(157.5, (float) $total->grand_total);
    }

    public function test_participante_puede_seleccionar_dos_o_mas_talleres(): void
    {
        $tallerA = \App\Models\Taller::factory()->create(['evento_id' => $this->evento->id, 'nombre' => 'Taller A', 'modalidad' => 'OPTIONAL', 'precio' => 0]);
        $sesionA = \App\Models\SesionCongreso::factory()->create(['evento_id' => $this->evento->id, 'taller_id' => $tallerA->id, 'fecha' => '2026-10-15', 'hora_inicio' => '09:00', 'hora_fin' => '10:00']);
        $tallerB = \App\Models\Taller::factory()->create(['evento_id' => $this->evento->id, 'nombre' => 'Taller B', 'modalidad' => 'OPTIONAL', 'precio' => 0]);
        $sesionB = \App\Models\SesionCongreso::factory()->create(['evento_id' => $this->evento->id, 'taller_id' => $tallerB->id, 'fecha' => '2026-10-15', 'hora_inicio' => '11:00', 'hora_fin' => '12:00']);
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $response = $this->postJson("/api/v1/event/{$this->evento->id}/registro-manual/bulk",
            $this->payload(['participantes' => [$this->participanteRow(['talleres' => 'Taller A; Taller B'])]])
        );

        $response->assertStatus(200)->assertJson(['success' => true]);
        $participante = Participante::first();
        $this->assertDatabaseHas('participante_taller_sesion', ['participante_id' => $participante->id, 'sesion_congreso_id' => $sesionA->id]);
        $this->assertDatabaseHas('participante_taller_sesion', ['participante_id' => $participante->id, 'sesion_congreso_id' => $sesionB->id]);
    }

    public function test_taller_obligatorio_sin_seleccionar_rechaza_solo_esa_fila(): void
    {
        \App\Models\Taller::factory()->create(['evento_id' => $this->evento->id, 'nombre' => 'Ética Médica', 'modalidad' => 'REQUIRED']);
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $response = $this->postJson("/api/v1/event/{$this->evento->id}/registro-manual/bulk", $this->payload());

        // 422 general NO — el archivo entero no se cae, la fila queda
        // reportada en "errores" como cualquier otro dato inválido.
        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertCount(0, $response->json('creados'));
        $errores = $response->json('errores');
        $this->assertCount(1, $errores);
        $this->assertStringContainsString('Ética Médica', $errores[0]['error']);
    }

    public function test_taller_obligatorio_con_seleccion_correcta_pasa(): void
    {
        $taller = \App\Models\Taller::factory()->create(['evento_id' => $this->evento->id, 'nombre' => 'Ética Médica', 'modalidad' => 'REQUIRED', 'precio' => 0]);
        \App\Models\SesionCongreso::factory()->create(['evento_id' => $this->evento->id, 'taller_id' => $taller->id]);
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $response = $this->postJson("/api/v1/event/{$this->evento->id}/registro-manual/bulk",
            $this->payload(['participantes' => [$this->participanteRow(['talleres' => 'ética médica'])]])
        );

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertCount(1, $response->json('creados'));
    }

    public function test_nombre_de_taller_inexistente_rechaza_solo_esa_fila_no_todo_el_archivo(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $filaValida = $this->participanteRow(['numero_documento' => '11111111']);
        $filaInvalida = $this->participanteRow(['numero_documento' => '22222222', 'talleres' => 'No Existe']);

        $response = $this->postJson("/api/v1/event/{$this->evento->id}/registro-manual/bulk",
            $this->payload(['participantes' => [$filaValida, $filaInvalida]])
        );

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertCount(1, $response->json('creados'));
        $errores = $response->json('errores');
        $this->assertCount(1, $errores);
        $this->assertStringContainsString('No Existe', $errores[0]['error']);
    }

    public function test_taller_con_mas_de_una_sesion_activa_rechaza_con_mensaje_claro(): void
    {
        $taller = \App\Models\Taller::factory()->create(['evento_id' => $this->evento->id, 'nombre' => 'Multi Horario', 'modalidad' => 'OPTIONAL']);
        \App\Models\SesionCongreso::factory()->create(['evento_id' => $this->evento->id, 'taller_id' => $taller->id, 'fecha' => '2026-10-15']);
        \App\Models\SesionCongreso::factory()->create(['evento_id' => $this->evento->id, 'taller_id' => $taller->id, 'fecha' => '2026-10-16']);
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $response = $this->postJson("/api/v1/event/{$this->evento->id}/registro-manual/bulk",
            $this->payload(['participantes' => [$this->participanteRow(['talleres' => 'Multi Horario'])]])
        );

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertCount(0, $response->json('creados'));
        $this->assertStringContainsString('más de un horario', $response->json('errores')[0]['error']);
    }

    /**
     * Desambiguación por horario (01/09/2026) — caso real reportado por el
     * usuario: un congreso con 2 talleres DISTINTOS (2 filas de `talleres`,
     * no 2 sesiones de uno solo) que comparten el nombre exacto, dictados
     * en horarios distintos. "Nombre@DD/MM HH:MM" elige cuál sin tener que
     * renombrar los talleres reales.
     */
    public function test_desambigua_por_horario_cuando_dos_talleres_comparten_nombre(): void
    {
        $tallerManana = \App\Models\Taller::factory()->create(['evento_id' => $this->evento->id, 'nombre' => 'REASE', 'modalidad' => 'OPTIONAL']);
        $sesionManana = \App\Models\SesionCongreso::factory()->create([
            'evento_id' => $this->evento->id, 'taller_id' => $tallerManana->id,
            'fecha' => '2026-10-16', 'hora_inicio' => '08:00', 'hora_fin' => '12:00',
        ]);
        $tallerTarde = \App\Models\Taller::factory()->create(['evento_id' => $this->evento->id, 'nombre' => 'REASE', 'modalidad' => 'OPTIONAL']);
        $sesionTarde = \App\Models\SesionCongreso::factory()->create([
            'evento_id' => $this->evento->id, 'taller_id' => $tallerTarde->id,
            'fecha' => '2026-10-16', 'hora_inicio' => '14:00', 'hora_fin' => '18:00',
        ]);

        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $response = $this->postJson("/api/v1/event/{$this->evento->id}/registro-manual/bulk",
            $this->payload(['participantes' => [$this->participanteRow(['talleres' => 'REASE@16/10 14:00'])]])
        );

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertCount(1, $response->json('creados'));

        $participante = Participante::first();
        $this->assertDatabaseHas('participante_taller_sesion', [
            'participante_id' => $participante->id,
            'sesion_congreso_id' => $sesionTarde->id,
        ]);
        $this->assertDatabaseMissing('participante_taller_sesion', [
            'participante_id' => $participante->id,
            'sesion_congreso_id' => $sesionManana->id,
        ]);
    }

    public function test_sin_horario_dos_talleres_con_mismo_nombre_pide_desambiguar(): void
    {
        \App\Models\Taller::factory()->create(['evento_id' => $this->evento->id, 'nombre' => 'REASE', 'modalidad' => 'OPTIONAL'])
            ->sesiones()->save(\App\Models\SesionCongreso::factory()->make(['evento_id' => $this->evento->id, 'fecha' => '2026-10-16', 'hora_inicio' => '08:00']));
        \App\Models\Taller::factory()->create(['evento_id' => $this->evento->id, 'nombre' => 'REASE', 'modalidad' => 'OPTIONAL'])
            ->sesiones()->save(\App\Models\SesionCongreso::factory()->make(['evento_id' => $this->evento->id, 'fecha' => '2026-10-16', 'hora_inicio' => '14:00']));

        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $response = $this->postJson("/api/v1/event/{$this->evento->id}/registro-manual/bulk",
            $this->payload(['participantes' => [$this->participanteRow(['talleres' => 'REASE'])]])
        );

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertCount(0, $response->json('creados'));
        $this->assertStringContainsString('@DD/MM HH:MM', $response->json('errores')[0]['error']);
    }

    public function test_horario_que_no_matchea_ninguna_sesion_rechaza_con_mensaje_claro(): void
    {
        \App\Models\Taller::factory()->create(['evento_id' => $this->evento->id, 'nombre' => 'REASE', 'modalidad' => 'OPTIONAL'])
            ->sesiones()->save(\App\Models\SesionCongreso::factory()->make(['evento_id' => $this->evento->id, 'fecha' => '2026-10-16', 'hora_inicio' => '08:00']));

        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        $response = $this->postJson("/api/v1/event/{$this->evento->id}/registro-manual/bulk",
            $this->payload(['participantes' => [$this->participanteRow(['talleres' => 'REASE@16/10 09:00'])]])
        );

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertCount(0, $response->json('creados'));
        $this->assertStringContainsString('No se encontró una sesión activa', $response->json('errores')[0]['error']);
    }
}

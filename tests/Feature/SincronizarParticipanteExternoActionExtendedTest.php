<?php

namespace Tests\Feature;

use App\Actions\SincronizarParticipanteExternoAction;
use App\Models\Ciudad;
use App\Models\Evento;
use App\Models\FormType;
use App\Models\Organizador;
use App\Models\Pais;
use App\Models\Participante;
use App\Models\ParticipanteTallerSesion;
use App\Models\SesionCongreso;
use App\Models\Souvenir;
use App\Models\SouvenirParticipante;
use App\Models\SubtipoEvento;
use App\Models\Taller;
use App\Models\TipoEvento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Extensión (17/09/2026) de SincronizarParticipanteExternoAction para el
 * caso de una fuente con PULL propio (ver App\Services\SyncExternoPullService)
 * — documento/género/fecha de nacimiento reales, souvenirs y talleres
 * matcheados por nombre. Llama a `run()` directo (no vía HTTP) porque estos
 * campos nunca llegan a través del webhook de COLABIOCLI (whitelist de
 * SyncExternoController::sync() los descarta — ver
 * SyncParticipanteExternoTest para la garantía de que esto NO le afecta).
 */
class SincronizarParticipanteExternoActionExtendedTest extends TestCase
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
            'publicado' => false,
        ]);

        $this->formType = FormType::factory()->create(['event_id' => $this->evento->id, 'name' => 'General']);
    }

    private function runAction(array $fila): array
    {
        return app(SincronizarParticipanteExternoAction::class)->run($this->evento, $this->formType, $fila);
    }

    public function test_documento_real_tiene_prioridad_sobre_correo_y_nombre(): void
    {
        $this->runAction([
            'nombre' => 'Juan', 'apellido' => 'Perez', 'correo' => 'juan@test.net',
            'numero_documento' => '12345678', 'tipo_documento' => 'CI',
        ]);

        $this->assertDatabaseHas('participantes', [
            'numero_documento' => '12345678', 'tipo_documento' => 'CI', 'correo' => 'juan@test.net',
        ]);
    }

    public function test_sin_tipo_documento_cae_a_ci_por_defecto(): void
    {
        $this->runAction(['nombre' => 'Juan', 'apellido' => 'Perez', 'numero_documento' => '12345678']);

        $this->assertDatabaseHas('participantes', ['numero_documento' => '12345678', 'tipo_documento' => 'CI']);
    }

    public function test_reenviar_con_el_mismo_documento_actualiza_no_duplica(): void
    {
        $this->runAction(['nombre' => 'Juan', 'apellido' => 'Perez', 'numero_documento' => '12345678']);
        $this->runAction(['nombre' => 'Juan', 'apellido' => 'Perez Actualizado', 'numero_documento' => '12345678']);

        $this->assertDatabaseCount('participantes', 1);
        $this->assertDatabaseHas('participantes', ['numero_documento' => '12345678', 'apellido' => 'Perez Actualizado']);
    }

    public function test_genero_real_valido_se_guarda(): void
    {
        $this->runAction(['nombre' => 'Ana', 'apellido' => 'Test', 'correo' => 'ana@test.net', 'genero' => 'Femenino']);

        $this->assertDatabaseHas('participantes', ['numero_documento' => 'ana@test.net', 'genero' => 'Femenino']);
    }

    public function test_genero_invalido_o_ausente_cae_a_otro(): void
    {
        $this->runAction(['nombre' => 'Ana', 'apellido' => 'Test', 'correo' => 'ana@test.net', 'genero' => 'No binario']);

        $this->assertDatabaseHas('participantes', ['numero_documento' => 'ana@test.net', 'genero' => 'Otro']);
    }

    public function test_fecha_nacimiento_real_calcula_edad(): void
    {
        $fechaNacimiento = now()->subYears(30)->toDateString();

        $this->runAction([
            'nombre' => 'Ana', 'apellido' => 'Test', 'correo' => 'ana@test.net',
            'fecha_nacimiento' => $fechaNacimiento,
        ]);

        $this->assertDatabaseHas('participantes', [
            'numero_documento' => 'ana@test.net', 'fecha_nacimiento' => $fechaNacimiento, 'edad' => 30,
        ]);
    }

    public function test_sin_fecha_nacimiento_cae_al_sentinel_actual(): void
    {
        $this->runAction(['nombre' => 'Ana', 'apellido' => 'Test', 'correo' => 'ana@test.net']);

        $this->assertDatabaseHas('participantes', [
            'numero_documento' => 'ana@test.net', 'fecha_nacimiento' => '1900-01-01', 'edad' => 0,
        ]);
    }

    public function test_souvenir_matcheado_crea_souvenir_participante_con_datos_del_catalogo(): void
    {
        Souvenir::factory()->create([
            'form_types_id' => $this->formType->id, 'name' => 'Polera',
            'price' => 45.50, 'requiere_talla' => true, 'requiere_sexo' => true,
        ]);

        $this->runAction([
            'nombre' => 'Ana', 'apellido' => 'Test', 'correo' => 'ana@test.net',
            'souvenirs' => [['nombre' => 'polera', 'talla' => 'M', 'sexo' => 'Femenino']],
        ]);

        $participante = Participante::where('numero_documento', 'ana@test.net')->first();

        $this->assertDatabaseHas('souvenir_participantes', [
            'participante_id' => $participante->id, 'nombre' => 'Polera',
            'precio' => 45.50, 'talla' => 'M', 'sexo' => 'Femenino',
        ]);
    }

    public function test_souvenir_sin_talla_sexo_requeridos_no_los_guarda(): void
    {
        Souvenir::factory()->create([
            'form_types_id' => $this->formType->id, 'name' => 'Bolsa',
            'requiere_talla' => false, 'requiere_sexo' => false,
        ]);

        $this->runAction([
            'nombre' => 'Ana', 'apellido' => 'Test', 'correo' => 'ana@test.net',
            'souvenirs' => [['nombre' => 'Bolsa', 'talla' => 'M', 'sexo' => 'Femenino']],
        ]);

        $this->assertDatabaseHas('souvenir_participantes', ['nombre' => 'Bolsa', 'talla' => null, 'sexo' => null]);
    }

    public function test_souvenir_sin_match_se_omite_sin_tumbar_el_participante(): void
    {
        $resultado = $this->runAction([
            'nombre' => 'Ana', 'apellido' => 'Test', 'correo' => 'ana@test.net',
            'souvenirs' => [['nombre' => 'No Existe']],
        ]);

        $this->assertSame('creado', $resultado['resultado']);
        $this->assertDatabaseCount('souvenir_participantes', 0);
    }

    public function test_taller_matcheado_con_una_sola_sesion_crea_participante_taller_sesion_con_precio_real(): void
    {
        $this->evento->update(['talleres_con_costo' => true]);
        $taller = Taller::factory()->create(['evento_id' => $this->evento->id, 'nombre' => 'Workshop A', 'precio' => 100]);
        $sesion = SesionCongreso::factory()->create(['evento_id' => $this->evento->id, 'taller_id' => $taller->id, 'titulo' => 'Sesión única']);

        $this->runAction([
            'nombre' => 'Ana', 'apellido' => 'Test', 'correo' => 'ana@test.net',
            'talleres' => [['taller' => 'workshop a']],
        ]);

        $participante = Participante::where('numero_documento', 'ana@test.net')->first();

        $this->assertDatabaseHas('participante_taller_sesion', [
            'participante_id' => $participante->id, 'sesion_congreso_id' => $sesion->id,
            'taller_id' => $taller->id, 'unit_price' => 100, 'total' => 100, 'pago_pendiente' => false,
        ]);
    }

    public function test_taller_con_2_sesiones_desambigua_por_nombre_de_sesion(): void
    {
        $taller = Taller::factory()->create(['evento_id' => $this->evento->id, 'nombre' => 'Workshop B']);
        $sesionA = SesionCongreso::factory()->create(['evento_id' => $this->evento->id, 'taller_id' => $taller->id, 'titulo' => 'Turno mañana']);
        SesionCongreso::factory()->create(['evento_id' => $this->evento->id, 'taller_id' => $taller->id, 'titulo' => 'Turno tarde']);

        $this->runAction([
            'nombre' => 'Ana', 'apellido' => 'Test', 'correo' => 'ana@test.net',
            'talleres' => [['taller' => 'Workshop B', 'sesion' => 'Turno mañana']],
        ]);

        $participante = Participante::where('numero_documento', 'ana@test.net')->first();
        $this->assertDatabaseHas('participante_taller_sesion', ['participante_id' => $participante->id, 'sesion_congreso_id' => $sesionA->id]);
        $this->assertDatabaseCount('participante_taller_sesion', 1);
    }

    public function test_taller_con_2_sesiones_sin_desambiguar_se_omite(): void
    {
        $taller = Taller::factory()->create(['evento_id' => $this->evento->id, 'nombre' => 'Workshop C']);
        SesionCongreso::factory()->create(['evento_id' => $this->evento->id, 'taller_id' => $taller->id, 'titulo' => 'Turno mañana']);
        SesionCongreso::factory()->create(['evento_id' => $this->evento->id, 'taller_id' => $taller->id, 'titulo' => 'Turno tarde']);

        $this->runAction([
            'nombre' => 'Ana', 'apellido' => 'Test', 'correo' => 'ana@test.net',
            'talleres' => [['taller' => 'Workshop C']],
        ]);

        $this->assertDatabaseCount('participante_taller_sesion', 0);
    }

    public function test_taller_sin_match_se_omite_sin_tumbar_el_participante(): void
    {
        $resultado = $this->runAction([
            'nombre' => 'Ana', 'apellido' => 'Test', 'correo' => 'ana@test.net',
            'talleres' => [['taller' => 'No Existe']],
        ]);

        $this->assertSame('creado', $resultado['resultado']);
        $this->assertDatabaseCount('participante_taller_sesion', 0);
    }

    public function test_reenviar_taller_no_duplica_la_fila(): void
    {
        $taller = Taller::factory()->create(['evento_id' => $this->evento->id, 'nombre' => 'Workshop D']);
        SesionCongreso::factory()->create(['evento_id' => $this->evento->id, 'taller_id' => $taller->id, 'titulo' => 'Única']);

        $fila = ['nombre' => 'Ana', 'apellido' => 'Test', 'correo' => 'ana@test.net', 'talleres' => [['taller' => 'Workshop D']]];
        $this->runAction($fila);
        $this->runAction($fila);

        $this->assertDatabaseCount('participante_taller_sesion', 1);
    }

    /**
     * Fix (23/09/2026) — correo/categoria nunca se actualizaban después de
     * creado el participante (solo se grababan en el alta). Ver memoria
     * del proyecto / plan del fix.
     */
    public function test_correo_se_actualiza_cuando_la_fuente_manda_un_valor_nuevo(): void
    {
        $this->runAction([
            'nombre' => 'Ana', 'apellido' => 'Test', 'numero_documento' => '111',
            'correo' => 'viejo@test.net',
        ]);
        $this->runAction([
            'nombre' => 'Ana', 'apellido' => 'Test', 'numero_documento' => '111',
            'correo' => 'nuevo@test.net',
        ]);

        $this->assertDatabaseCount('participantes', 1);
        $this->assertDatabaseHas('participantes', ['numero_documento' => '111', 'correo' => 'nuevo@test.net']);
    }

    public function test_correo_se_preserva_cuando_la_fuente_lo_omite(): void
    {
        $this->runAction([
            'nombre' => 'Ana', 'apellido' => 'Test', 'numero_documento' => '111',
            'correo' => 'ana@test.net',
        ]);
        $this->runAction(['nombre' => 'Ana', 'apellido' => 'Test Actualizado', 'numero_documento' => '111']);

        $this->assertDatabaseHas('participantes', [
            'numero_documento' => '111', 'apellido' => 'Test Actualizado', 'correo' => 'ana@test.net',
        ]);
    }

    /**
     * Categoria ya se actualizaba antes de este fix (texto libre, sin
     * resolver contra el catálogo) — se agrega como test de regresión
     * formal a pedido del usuario, no había cobertura dedicada.
     */
    public function test_categoria_se_actualiza_cuando_la_fuente_la_cambia(): void
    {
        $this->runAction(['nombre' => 'Ana', 'apellido' => 'Test', 'numero_documento' => '111', 'categoria' => '5K']);
        $this->runAction(['nombre' => 'Ana', 'apellido' => 'Test', 'numero_documento' => '111', 'categoria' => '10K']);

        $this->assertDatabaseHas('participantes', ['numero_documento' => '111', 'categoria' => '10K']);
    }

    /**
     * Hallazgo real: numero_documento es a la vez un dato normal Y la
     * clave de matching — si la fuente lo corrige (ej. typo de CI), el
     * sync sin external_id no reconoce la fila vieja y crea un duplicado.
     */
    public function test_sin_external_id_un_cambio_de_documento_crea_duplicado_comportamiento_preexistente(): void
    {
        $this->runAction(['nombre' => 'Juan', 'apellido' => 'Perez', 'numero_documento' => '111']);
        $this->runAction(['nombre' => 'Juan', 'apellido' => 'Perez', 'numero_documento' => '222']);

        $this->assertDatabaseCount('participantes', 2);
    }

    public function test_con_external_id_un_cambio_de_documento_actualiza_el_mismo_participante_no_duplica(): void
    {
        $this->runAction([
            'nombre' => 'Juan', 'apellido' => 'Perez', 'numero_documento' => '111',
            '_origen_sync_externo' => 'pull:1:EXT-777',
        ]);
        $this->runAction([
            'nombre' => 'Juan', 'apellido' => 'Perez', 'numero_documento' => '222',
            '_origen_sync_externo' => 'pull:1:EXT-777',
        ]);

        $this->assertDatabaseCount('participantes', 1);
        $this->assertDatabaseHas('participantes', ['numero_documento' => '222']);
        $this->assertDatabaseHas('registrations', ['origen_sync_externo' => 'pull:1:EXT-777']);
    }

    /**
     * Auto-backfill: una fila creada SIN external_id que en una corrida
     * posterior sí lo trae, lo graba en esa misma fila — así una config
     * real que ya está corriendo en UAT queda protegida apenas la fuente
     * arranque a mandarlo, sin necesitar backfill manual.
     */
    public function test_external_id_que_llega_despues_se_graba_en_la_fila_existente_sin_duplicar(): void
    {
        $this->runAction(['nombre' => 'Juan', 'apellido' => 'Perez', 'numero_documento' => '111']);
        $this->runAction([
            'nombre' => 'Juan', 'apellido' => 'Perez', 'numero_documento' => '111',
            '_origen_sync_externo' => 'pull:1:EXT-888',
        ]);

        $this->assertDatabaseCount('participantes', 1);
        $this->assertDatabaseHas('registrations', ['origen_sync_externo' => 'pull:1:EXT-888']);

        // Y a partir de acá, un cambio de documento con la misma clave ya
        // no duplica — confirma que el backfill quedó realmente activo.
        $this->runAction([
            'nombre' => 'Juan', 'apellido' => 'Perez', 'numero_documento' => '333',
            '_origen_sync_externo' => 'pull:1:EXT-888',
        ]);
        $this->assertDatabaseCount('participantes', 1);
        $this->assertDatabaseHas('participantes', ['numero_documento' => '333']);
    }
}

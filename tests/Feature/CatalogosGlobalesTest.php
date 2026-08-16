<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ciudad;
use App\Models\Evento;
use App\Models\Organizador;
use App\Models\Pais;
use App\Models\RelacionContacto;
use App\Models\Sexo;
use App\Models\SubtipoEvento;
use App\Models\TipoEvento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CRUD de los catálogos globales (País, Ciudad, Sexo, Tipo/Subtipo de
 * evento, Relación de contacto) — solo super_admin. Ver
 * elascenso/event/brain/PLAN-CATALOGOS-GLOBALES-15082026.md.
 */
class CatalogosGlobalesTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsSuperAdmin()
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'super_admin']);

        return $admin;
    }

    public function test_admin_no_superadmin_no_puede_ver_ningun_catalogo(): void
    {
        $admin = $this->actingAsAdmin();
        $admin->update(['rol' => 'admin']);

        $this->getJson('/api/v1/catalogos/paises')->assertStatus(403);
        $this->getJson('/api/v1/catalogos/ciudades')->assertStatus(403);
        $this->getJson('/api/v1/catalogos/sexos')->assertStatus(403);
        $this->getJson('/api/v1/catalogos/tipos-evento')->assertStatus(403);
        $this->getJson('/api/v1/catalogos/subtipos-evento')->assertStatus(403);
        $this->getJson('/api/v1/catalogos/relaciones-contacto')->assertStatus(403);
    }

    // ── País ─────────────────────────────────────────────────────────

    public function test_pais_store_y_destroy_bloqueado_por_ciudad(): void
    {
        $this->actingAsSuperAdmin();

        $this->postJson('/api/v1/catalogos/paises', ['nombre' => 'Boliviana', 'iso2' => 'BO'])
            ->assertStatus(201)
            ->assertJsonPath('data.nombre', 'Boliviana');

        $pais = Pais::where('nombre', 'Boliviana')->first();
        Ciudad::create(['pais_id' => $pais->id, 'nombre' => 'La Paz', 'activo' => true]);

        $this->deleteJson("/api/v1/catalogos/paises/{$pais->id}")->assertStatus(409);
        $this->assertDatabaseHas('paises', ['id' => $pais->id]);
    }

    public function test_pais_destroy_permite_borrar_sin_dependientes(): void
    {
        $this->actingAsSuperAdmin();
        $pais = Pais::create(['nombre' => 'Testland', 'iso2' => 'TL', 'activo' => true]);

        $this->deleteJson("/api/v1/catalogos/paises/{$pais->id}")->assertStatus(200);
        $this->assertDatabaseMissing('paises', ['id' => $pais->id]);
    }

    // ── Ciudad ───────────────────────────────────────────────────────

    public function test_ciudad_store_y_destroy_bloqueada_por_organizador(): void
    {
        $this->actingAsSuperAdmin();
        $pais = Pais::create(['nombre' => 'Testland', 'iso2' => 'TL', 'activo' => true]);

        $this->postJson('/api/v1/catalogos/ciudades', ['pais_id' => $pais->id, 'nombre' => 'Test City'])
            ->assertStatus(201);

        $ciudad = Ciudad::where('nombre', 'Test City')->first();
        Organizador::factory()->create(['pais_id' => $pais->id, 'ciudad_id' => $ciudad->id]);

        $this->deleteJson("/api/v1/catalogos/ciudades/{$ciudad->id}")->assertStatus(409);
        $this->assertDatabaseHas('ciudades', ['id' => $ciudad->id]);
    }

    public function test_ciudad_requiere_pais_existente(): void
    {
        $this->actingAsSuperAdmin();

        $this->postJson('/api/v1/catalogos/ciudades', ['pais_id' => 999999, 'nombre' => 'X'])
            ->assertStatus(422);
    }

    // ── Sexo ─────────────────────────────────────────────────────────

    public function test_sexo_store_y_destroy_bloqueado_por_categoria(): void
    {
        $this->actingAsSuperAdmin();

        $this->postJson('/api/v1/catalogos/sexos', ['nombre' => 'Otro'])
            ->assertStatus(201);
        $sexo = Sexo::where('nombre', 'Otro')->first();

        $categoria = Category::factory()->create(['sexo_id' => $sexo->id]);

        $this->deleteJson("/api/v1/catalogos/sexos/{$sexo->id}")->assertStatus(409);
        $this->assertDatabaseHas('sexos', ['id' => $sexo->id]);

        // Liberando la categoría, ahora sí se puede borrar.
        $categoria->update(['sexo_id' => null]);
        $this->deleteJson("/api/v1/catalogos/sexos/{$sexo->id}")->assertStatus(200);
    }

    // ── Tipo de evento ───────────────────────────────────────────────

    public function test_tipos_evento_publico_no_cambia_con_el_crud_nuevo(): void
    {
        // Regresión: el endpoint público (sin auth, activo-only, anidado)
        // no debe verse afectado por adminIndex/store/update/destroy.
        // (event_testing arranca sin seed de tipos_evento — el seeder solo
        // corrió contra la BD real — así que se crea acá explícitamente.)
        TipoEvento::create(['nombre' => 'Deportivo', 'activo' => true]);

        $this->getJson('/api/v1/tipos-evento')
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'tiposEvento' => [['id', 'nombre', 'icono', 'subtipos']]]);
    }

    public function test_tipo_evento_admin_index_y_store(): void
    {
        $this->actingAsSuperAdmin();

        $this->getJson('/api/v1/catalogos/tipos-evento')->assertStatus(200);

        $this->postJson('/api/v1/catalogos/tipos-evento', ['nombre' => 'Deporte extremo'])
            ->assertStatus(201)
            ->assertJsonPath('data.nombre', 'Deporte extremo');
    }

    public function test_tipo_evento_destroy_bloqueado_por_subtipo_y_por_evento(): void
    {
        $this->actingAsSuperAdmin();
        $tipo = TipoEvento::create(['nombre' => 'Tipo X', 'activo' => true]);

        SubtipoEvento::create(['tipo_evento_id' => $tipo->id, 'nombre' => 'Subtipo X', 'activo' => true]);
        $this->deleteJson("/api/v1/catalogos/tipos-evento/{$tipo->id}")->assertStatus(409);

        SubtipoEvento::where('tipo_evento_id', $tipo->id)->delete();

        $organizador = Organizador::factory()->create();
        Evento::factory()->create(['organizador_id' => $organizador->id, 'tipo_evento_id' => $tipo->id]);
        $this->deleteJson("/api/v1/catalogos/tipos-evento/{$tipo->id}")->assertStatus(409);
    }

    // ── Subtipo de evento ────────────────────────────────────────────

    public function test_subtipo_evento_store_y_destroy_bloqueado_por_evento(): void
    {
        $this->actingAsSuperAdmin();
        $tipo = TipoEvento::create(['nombre' => 'Tipo Z', 'activo' => true]);

        $this->postJson('/api/v1/catalogos/subtipos-evento', ['tipo_evento_id' => $tipo->id, 'nombre' => 'Subtipo Y'])
            ->assertStatus(201);
        $subtipo = SubtipoEvento::where('nombre', 'Subtipo Y')->first();

        $organizador = Organizador::factory()->create();
        Evento::factory()->create(['organizador_id' => $organizador->id, 'subtipo_evento_id' => $subtipo->id]);

        $this->deleteJson("/api/v1/catalogos/subtipos-evento/{$subtipo->id}")->assertStatus(409);
    }

    // ── Relación de contacto ─────────────────────────────────────────

    public function test_relacion_contacto_store_update_destroy(): void
    {
        $this->actingAsSuperAdmin();

        $this->postJson('/api/v1/catalogos/relaciones-contacto', ['nombre' => 'Tutor/a'])
            ->assertStatus(201);
        $relacion = RelacionContacto::where('nombre', 'Tutor/a')->first();

        $this->putJson("/api/v1/catalogos/relaciones-contacto/{$relacion->id}", ['activo' => false])
            ->assertStatus(200)
            ->assertJsonPath('data.activo', false);

        $this->deleteJson("/api/v1/catalogos/relaciones-contacto/{$relacion->id}")->assertStatus(200);
        $this->assertDatabaseMissing('relaciones_contacto', ['id' => $relacion->id]);
    }
}

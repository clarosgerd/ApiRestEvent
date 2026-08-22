<?php

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use App\Models\Pais;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Consolidación monolito (21/08/2026), Fase 1a — mismo smoke test que
 * CatalogosPaisesTest, para los 6 catálogos restantes. Cubre índice +
 * CRUD completo de cada uno, con atención a las 2 particularidades reales
 * del código (no genéricas): TipoEvento delega en `adminIndex()`, no
 * `index()` (ese sigue siendo el endpoint público); Ciudad y
 * SubtipoEvento necesitan un segundo catálogo relacionado para el select
 * del formulario (país / tipo de evento). Ver
 * ApiRestEvent/brain/api_rest_event/PLAN-CONSOLIDACION-MONOLITO-21082026.md.
 */
class CatalogosRestantesTest extends TestCase
{
    use RefreshDatabase;

    private array $session;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = AdminUser::create([
            'nombre' => 'Super', 'email' => 'super@example.net',
            'password' => Hash::make('secret123'), 'rol' => 'super_admin',
            'activo' => true, 'evento_id' => null,
        ]);
        $token = $admin->createToken('auth-token')->plainTextToken;
        $this->session = ['admin_token' => $token, 'admin_user' => ['rol' => 'super_admin']];
    }

    public function test_sexo_crud_completo(): void
    {
        $this->withSession($this->session)->get('/admin/catalogos/sexos')
            ->assertOk()->assertViewIs('admin.catalogos.sexos');

        $create = $this->withSession($this->session)->post('/admin/catalogos/sexos', ['nombre' => 'SexoTest', 'activo' => 1]);
        $create->assertRedirect(route('admin.catalogos.sexos.index'));
        $sexo = \App\Models\Sexo::where('nombre', 'SexoTest')->firstOrFail();

        $this->withSession($this->session)->put("/admin/catalogos/sexos/{$sexo->id}", ['nombre' => 'SexoEditado', 'activo' => 1])
            ->assertRedirect(route('admin.catalogos.sexos.index'));
        $this->assertDatabaseHas('sexos', ['id' => $sexo->id, 'nombre' => 'SexoEditado']);

        $this->withSession($this->session)->delete("/admin/catalogos/sexos/{$sexo->id}")
            ->assertRedirect(route('admin.catalogos.sexos.index'));
        $this->assertDatabaseMissing('sexos', ['id' => $sexo->id]);
    }

    public function test_relacion_contacto_crud_completo(): void
    {
        $this->withSession($this->session)->get('/admin/catalogos/relaciones-contacto')
            ->assertOk()->assertViewIs('admin.catalogos.relaciones-contacto');

        $create = $this->withSession($this->session)->post('/admin/catalogos/relaciones-contacto', ['nombre' => 'RelacionTest', 'activo' => 1]);
        $create->assertRedirect(route('admin.catalogos.relaciones-contacto.index'));
        $rel = \App\Models\RelacionContacto::where('nombre', 'RelacionTest')->firstOrFail();

        $this->withSession($this->session)->delete("/admin/catalogos/relaciones-contacto/{$rel->id}")
            ->assertRedirect(route('admin.catalogos.relaciones-contacto.index'));
        $this->assertDatabaseMissing('relaciones_contacto', ['id' => $rel->id]);
    }

    public function test_formas_pago_crud_completo(): void
    {
        $this->withSession($this->session)->get('/admin/catalogos/formas-pago')
            ->assertOk()->assertViewIs('admin.catalogos.formas-pago');

        $create = $this->withSession($this->session)->post('/admin/catalogos/formas-pago', [
            'slug' => 'meru-test', 'nombre' => 'Meru Test', 'descripcion' => 'x', 'pasarela' => 'meru', 'tipo' => 'integrado', 'activo' => 1,
        ]);
        $create->assertRedirect(route('admin.catalogos.formas-pago.index'));
        $this->assertDatabaseHas('formas_pagos', ['slug' => 'meru-test', 'organizador_id' => null]);
    }

    public function test_tipo_evento_index_usa_adminindex_no_el_endpoint_publico(): void
    {
        $response = $this->withSession($this->session)->get('/admin/catalogos/tipos-evento');
        $response->assertOk()->assertViewIs('admin.catalogos.tipos-evento');
        // adminIndex() devuelve TipoEvento::orderBy('nombre')->get() plano
        // (sin envolver en 'tiposEvento' con subtipos anidados como el
        // index() público) — confirma que se llamó al método correcto.
        $response->assertViewHas('tipos');

        $create = $this->withSession($this->session)->post('/admin/catalogos/tipos-evento', ['nombre' => 'TipoTest', 'icono' => '🏃', 'activo' => 1]);
        $create->assertRedirect(route('admin.catalogos.tipos-evento.index'));
        $this->assertDatabaseHas('tipos_evento', ['nombre' => 'TipoTest']);
    }

    public function test_subtipo_evento_index_trae_tipos_para_el_select(): void
    {
        $tipo = \App\Models\TipoEvento::create(['nombre' => 'TipoParaSubtipo', 'icono' => '🏃', 'activo' => true]);

        $response = $this->withSession($this->session)->get('/admin/catalogos/subtipos-evento');
        $response->assertOk()->assertViewIs('admin.catalogos.subtipos-evento');
        $response->assertViewHas('tipos');

        $create = $this->withSession($this->session)->post('/admin/catalogos/subtipos-evento', [
            'tipo_evento_id' => $tipo->id, 'nombre' => 'SubtipoTest', 'activo' => 1,
        ]);
        $create->assertRedirect(route('admin.catalogos.subtipos-evento.index'));
        $this->assertDatabaseHas('subtipos_evento', ['nombre' => 'SubtipoTest', 'tipo_evento_id' => $tipo->id]);
    }

    public function test_ciudad_index_trae_paises_para_el_select(): void
    {
        $pais = Pais::create(['nombre' => 'PaisParaCiudad', 'iso2' => 'PC', 'activo' => true]);

        $response = $this->withSession($this->session)->get('/admin/catalogos/ciudades');
        $response->assertOk()->assertViewIs('admin.catalogos.ciudades');
        $response->assertViewHas('paises');

        $create = $this->withSession($this->session)->post('/admin/catalogos/ciudades', [
            'pais_id' => $pais->id, 'nombre' => 'CiudadTest', 'activo' => 1,
        ]);
        $create->assertRedirect(route('admin.catalogos.ciudades.index'));
        $this->assertDatabaseHas('ciudades', ['nombre' => 'CiudadTest', 'pais_id' => $pais->id]);
    }
}

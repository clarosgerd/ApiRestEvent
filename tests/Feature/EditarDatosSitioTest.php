<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ciudad;
use App\Models\Evento;
use App\Models\FormType;
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
 * Editar datos del participante desde el POS de retiro en sitio
 * (elascenso/delivery, 28/09/2026) — corrección al momento de la entrega del
 * kit: nombre/apellido/género/fecha de nacimiento libres, categoría solo si
 * el precio de la nueva coincide con el de la actual (si no, "debe pasar por
 * Caja"). Push-back sin sesión, mismo patrón firmado que
 * actualizarNumeracionSitio/confirmarPagoSitio.
 */
class EditarDatosSitioTest extends TestCase
{
    use RefreshDatabase;

    private Evento $evento;
    private FormType $formType;
    private Category $categoria;
    private Participante $participante;

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
        ]);
        $this->formType = FormType::factory()->create(['event_id' => $this->evento->id]);
        $this->categoria = Category::factory()->create(['event_id' => $this->evento->id, 'name' => '5K', 'price' => 50]);

        $registration = Registration::create([
            'referencia' => 'REF' . rand(100000, 999999), 'fecha' => now(), 'evento_id' => $this->evento->id,
            'form_types_id' => $this->formType->id, 'evento_nombre' => $this->evento->nombre,
            'tipo_pago' => 'EFECTIVO', 'pago_status' => 'paid',
        ]);
        $this->participante = Participante::create([
            'registration_id' => $registration->id,
            'nombre' => 'Ana', 'apellido' => 'Prueba', 'genero' => 'Femenino',
            'tipo_documento' => 'DNI', 'numero_documento' => '12345678',
            'fecha_nacimiento' => '1995-01-01', 'edad' => 30,
            'correo' => 'ana@test.net', 'direccion' => 'x', 'ciudad' => 'x', 'telefono' => '123',
            'categoria' => $this->categoria->id, 'precio_categoria' => 50, 'subtotal' => 50,
        ]);
    }

    private function url(): string
    {
        return URL::signedRoute('organizador.dashboard.editar-datos-sitio', [
            'evento' => $this->evento->id, 'documento' => $this->participante->numero_documento,
        ]);
    }

    public function test_actualiza_nombre_apellido_genero_y_fecha_de_nacimiento(): void
    {
        $this->getJson($this->url() . '&nombre=Andrea&apellido=Gomez&genero=Masculino&fecha_nacimiento=1990-05-10')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('participante.nombre', 'Andrea')
            ->assertJsonPath('participante.genero', 'Masculino')
            ->assertJsonPath('participante.fechaNacimiento', '1990-05-10');

        $this->assertDatabaseHas('participantes', [
            'id' => $this->participante->id, 'nombre' => 'Andrea', 'apellido' => 'Gomez', 'genero' => 'Masculino',
        ]);
    }

    public function test_categoria_con_el_mismo_precio_se_acepta(): void
    {
        $otra = Category::factory()->create(['event_id' => $this->evento->id, 'name' => '10K', 'price' => 50]);

        $this->getJson($this->url() . '&categoria_id=' . $otra->id)
            ->assertOk()
            ->assertJsonPath('participante.categoriaId', (string) $otra->id);

        $this->assertDatabaseHas('participantes', [
            'id' => $this->participante->id, 'categoria' => (string) $otra->id, 'precio_categoria' => 50,
        ]);
    }

    public function test_categoria_con_precio_distinto_se_rechaza_sin_tocar_nada(): void
    {
        $otra = Category::factory()->create(['event_id' => $this->evento->id, 'name' => '15K', 'price' => 80]);

        $this->getJson($this->url() . '&nombre=Otro%20Nombre&categoria_id=' . $otra->id)
            ->assertStatus(422)
            ->assertJsonPath('error', 'El precio de esa categoría es distinto — debe pasar por Caja.');

        // Todo o nada: el nombre tampoco se aplicó.
        $this->assertDatabaseHas('participantes', [
            'id' => $this->participante->id, 'nombre' => 'Ana', 'categoria' => (string) $this->categoria->id,
        ]);
    }

    public function test_categoria_de_otro_evento_se_rechaza(): void
    {
        $otroEvento = Evento::factory()->create([
            'organizador_id' => $this->evento->organizador_id, 'tipo_evento_id' => $this->evento->tipo_evento_id,
            'subtipo_evento_id' => $this->evento->subtipo_evento_id, 'pais_id' => $this->evento->pais_id,
            'ciudad_id' => $this->evento->ciudad_id,
        ]);
        $ajena = Category::factory()->create(['event_id' => $otroEvento->id, 'price' => 50]);

        $this->getJson($this->url() . '&categoria_id=' . $ajena->id)
            ->assertStatus(422)
            ->assertJsonPath('error', 'Esa categoría no es válida para este participante.');
    }

    public function test_categoria_deshabilitada_se_rechaza(): void
    {
        $deshabilitada = Category::factory()->create([
            'event_id' => $this->evento->id, 'price' => 50, 'permite_inscripcion' => false,
        ]);

        $this->getJson($this->url() . '&categoria_id=' . $deshabilitada->id)->assertStatus(422);
    }

    public function test_genero_fuera_del_catalogo_se_rechaza(): void
    {
        $this->getJson($this->url() . '&genero=Invalido')->assertStatus(422);
    }

    public function test_documento_inexistente_da_404(): void
    {
        $url = URL::signedRoute('organizador.dashboard.editar-datos-sitio', [
            'evento' => $this->evento->id, 'documento' => '00000000',
        ]);

        $this->getJson($url . '&nombre=X')->assertNotFound();
    }

    public function test_firma_invalida_da_403(): void
    {
        $this->getJson($this->url() . '&nombre=X&signature=alterada')->assertForbidden();
    }
}

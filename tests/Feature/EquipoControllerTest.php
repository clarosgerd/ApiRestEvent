<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Ciudad;
use App\Models\Equipo;
use App\Models\Evento;
use App\Models\FormType;
use App\Models\Organizador;
use App\Models\Pais;
use App\Models\Participante;
use App\Models\Registration;
use App\Models\SubtipoEvento;
use App\Models\TipoEvento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Catálogo de equipos por evento (01/09/2026) — ver
 * brain/PLAN-CATALOGO-EQUIPOS-01092026.md. Antes de este cambio, GET/POST
 * /event/{id}/equipos no tenían auth (cualquiera podía inyectar equipos en
 * cualquier evento sin loguearse) y no existían update/destroy. Mismo
 * criterio de scoping que TallerCrudTest: admin de su propio evento, o
 * super_admin.
 */
class EquipoControllerTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): AdminUser
    {
        return AdminUser::factory()->create(['rol' => 'super_admin', 'evento_id' => null]);
    }

    private function adminDeEvento(int $eventoId): AdminUser
    {
        return AdminUser::factory()->create(['rol' => 'admin', 'evento_id' => $eventoId]);
    }

    private function evento(): Evento
    {
        $pais = Pais::first() ?? Pais::factory()->create();
        $ciudad = Ciudad::where('pais_id', $pais->id)->first() ?? Ciudad::factory()->create(['pais_id' => $pais->id]);
        $organizador = Organizador::first() ?? Organizador::factory()->create();
        $tipo = TipoEvento::first() ?? TipoEvento::factory()->create();
        $subtipo = SubtipoEvento::where('tipo_evento_id', $tipo->id)->first()
            ?? SubtipoEvento::factory()->create(['tipo_evento_id' => $tipo->id]);

        return Evento::factory()->create([
            'organizador_id' => $organizador->id,
            'tipo_evento_id' => $tipo->id,
            'subtipo_evento_id' => $subtipo->id,
            'pais_id' => $pais->id,
            'ciudad_id' => $ciudad->id,
        ]);
    }

    public function test_alta_en_bloque_sin_auth_es_rechazada(): void
    {
        $evento = $this->evento();

        $resp = $this->postJson("/api/v1/event/{$evento->id}/equipos", ['equipos' => ['Team A']]);

        $resp->assertStatus(401);
        $this->assertSame(0, Equipo::where('event_id', $evento->id)->count());
    }

    public function test_admin_de_su_evento_puede_dar_de_alta_equipos_en_bloque(): void
    {
        $evento = $this->evento();
        $this->actingAsAdmin($this->adminDeEvento($evento->id));

        $resp = $this->postJson("/api/v1/event/{$evento->id}/equipos", [
            'equipos' => ['Team A', 'Team B', 'Team A'],
        ]);

        $resp->assertCreated();
        $this->assertCount(2, $resp->json('data'));
        $this->assertSame(2, Equipo::where('event_id', $evento->id)->count());
    }

    public function test_admin_puede_editar_el_nombre_de_un_equipo(): void
    {
        $evento = $this->evento();
        $equipo = Equipo::create(['event_id' => $evento->id, 'nombre' => 'Team A']);
        $this->actingAsAdmin($this->adminDeEvento($evento->id));

        $resp = $this->putJson("/api/v1/equipo/{$equipo->id}", ['nombre' => 'Team A Corregido']);

        $resp->assertOk();
        $this->assertSame('Team A Corregido', $equipo->fresh()->nombre);
    }

    public function test_admin_de_otro_evento_no_puede_editar(): void
    {
        $evento = $this->evento();
        $otroEvento = $this->evento();
        $equipo = Equipo::create(['event_id' => $evento->id, 'nombre' => 'Team A']);
        $this->actingAsAdmin($this->adminDeEvento($otroEvento->id));

        $resp = $this->putJson("/api/v1/equipo/{$equipo->id}", ['nombre' => 'Hackeado']);

        $resp->assertStatus(403);
        $this->assertSame('Team A', $equipo->fresh()->nombre);
    }

    public function test_admin_puede_borrar_un_equipo_sin_participantes(): void
    {
        $evento = $this->evento();
        $equipo = Equipo::create(['event_id' => $evento->id, 'nombre' => 'Team A']);
        $this->actingAsAdmin($this->adminDeEvento($evento->id));

        $resp = $this->deleteJson("/api/v1/equipo/{$equipo->id}");

        $resp->assertOk();
        $this->assertNull(Equipo::find($equipo->id));
    }

    public function test_no_se_puede_borrar_un_equipo_con_participantes(): void
    {
        $evento = $this->evento();
        $equipo = Equipo::create(['event_id' => $evento->id, 'nombre' => 'Team A']);
        $formType = FormType::factory()->create(['event_id' => $evento->id]);
        $registration = Registration::factory()->create([
            'evento_id' => $evento->id,
            'form_types_id' => $formType->id,
            'referencia' => 'TEST-EQUIPO-'.rand(100000, 999999),
            'fecha' => now(),
            'evento_nombre' => $evento->nombre,
            'tipo_pago' => 'pendiente',
            'pago_status' => 'paid',
        ]);
        // Participante::factory() tiene un campo 'email' que no existe en la
        // tabla real (usa 'correo') — pre-existente, no relacionado a este
        // feature; mismo esquive que ya usa FormularioCamposTest.
        Participante::create([
            'registration_id' => $registration->id,
            'equipo_id' => $equipo->id,
            'nombre' => 'Ana', 'apellido' => 'Prueba', 'genero' => 'Femenino',
            'tipo_documento' => 'DNI', 'numero_documento' => (string) rand(10000000, 99999999),
            'fecha_nacimiento' => '1995-01-01', 'edad' => 30, 'correo' => 'ana'.rand(1, 99999).'@test.net',
            'direccion' => 'x', 'ciudad' => 'x', 'telefono' => '123',
            'categoria' => '1', 'subtotal' => 50,
        ]);
        $this->actingAsAdmin($this->adminDeEvento($evento->id));

        $resp = $this->deleteJson("/api/v1/equipo/{$equipo->id}");

        $resp->assertStatus(409);
        $this->assertNotNull(Equipo::find($equipo->id));
    }

    public function test_super_admin_puede_listar_equipos_de_cualquier_evento(): void
    {
        $evento = $this->evento();
        Equipo::create(['event_id' => $evento->id, 'nombre' => 'Team A']);
        $this->actingAsAdmin($this->superAdmin());

        $resp = $this->getJson("/api/v1/event/{$evento->id}/equipos");

        $resp->assertOk();
        $this->assertCount(1, $resp->json('data'));
    }
}

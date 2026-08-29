<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Evento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin de evento asignado a varios eventos (28/08/2026) — ver
 * PLAN-ADMIN-MULTI-EVENTO-28082026.md. `evento_id` sigue siendo el evento
 * principal (sin cambios, ver los 43 tests existentes que lo usan
 * directo); `evento_ids_adicionales` es el campo nuevo, 100% opt-in.
 */
class AdminUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_admin_con_eventos_adicionales(): void
    {
        $this->actingAsAdmin(); // super_admin por default
        $principal = Evento::factory()->create();
        $adicional1 = Evento::factory()->create();
        $adicional2 = Evento::factory()->create();

        $response = $this->postJson('/api/v1/admin/users', [
            'nombre'    => 'Ana Admin',
            'email'     => 'ana.admin@test.net',
            'password'  => 'password123',
            'rol'       => 'admin',
            'evento_id' => $principal->id,
            'evento_ids_adicionales' => [$adicional1->id, $adicional2->id],
        ]);

        $response->assertCreated()->assertJsonPath('success', true);

        $admin = AdminUser::where('email', 'ana.admin@test.net')->first();
        $this->assertEqualsCanonicalizing(
            [$adicional1->id, $adicional2->id],
            $admin->eventosAdicionales()->pluck('eventos.id')->all()
        );
        $this->assertEqualsCanonicalizing(
            [$principal->id, $adicional1->id, $adicional2->id],
            $admin->eventoIds()
        );
    }

    public function test_store_rechaza_eventos_adicionales_para_cajero(): void
    {
        $this->actingAsAdmin();
        $evento = Evento::factory()->create();
        $otroEvento = Evento::factory()->create();

        $response = $this->postJson('/api/v1/admin/users', [
            'nombre'    => 'Un Cajero',
            'email'     => 'cajero.test@test.net',
            'password'  => 'password123',
            'rol'       => 'cajero',
            'evento_id' => $evento->id,
            'evento_ids_adicionales' => [$otroEvento->id],
        ]);

        $response->assertUnprocessable();
        $this->assertDatabaseMissing('admin_users', ['email' => 'cajero.test@test.net']);
    }

    public function test_update_resincroniza_eventos_adicionales(): void
    {
        $this->actingAsAdmin();
        $principal = Evento::factory()->create();
        $viejo = Evento::factory()->create();
        $nuevo = Evento::factory()->create();

        $admin = AdminUser::factory()->create(['rol' => 'admin', 'evento_id' => $principal->id]);
        $admin->eventosAdicionales()->sync([$viejo->id]);

        $response = $this->putJson("/api/v1/admin/users/{$admin->id}", [
            'rol' => 'admin',
            'evento_ids_adicionales' => [$nuevo->id],
        ]);

        $response->assertOk();
        $this->assertEqualsCanonicalizing([$nuevo->id], $admin->eventosAdicionales()->pluck('eventos.id')->all());
    }

    public function test_update_puede_vaciar_eventos_adicionales(): void
    {
        $this->actingAsAdmin();
        $principal = Evento::factory()->create();
        $adicional = Evento::factory()->create();

        $admin = AdminUser::factory()->create(['rol' => 'admin', 'evento_id' => $principal->id]);
        $admin->eventosAdicionales()->sync([$adicional->id]);

        $this->putJson("/api/v1/admin/users/{$admin->id}", [
            'rol' => 'admin',
            'evento_ids_adicionales' => [],
        ])->assertOk();

        $this->assertCount(0, $admin->eventosAdicionales()->get());
    }

    // ── AdminUser::tieneAccesoAEvento() / AuthorizesEventoScope ─────────

    public function test_admin_con_evento_adicional_puede_operar_ambos_eventos(): void
    {
        $principal = Evento::factory()->create();
        $adicional = Evento::factory()->create();
        $ajeno = Evento::factory()->create();

        $admin = AdminUser::factory()->create(['rol' => 'admin', 'evento_id' => $principal->id]);
        $admin->eventosAdicionales()->sync([$adicional->id]);

        $this->assertTrue($admin->tieneAccesoAEvento($principal->id));
        $this->assertTrue($admin->tieneAccesoAEvento($adicional->id));
        $this->assertFalse($admin->tieneAccesoAEvento($ajeno->id));
    }

    /**
     * Regresión: el comportamiento de un admin SIN eventos adicionales
     * (el caso de los 43 tests existentes en la suite) no puede cambiar
     * — es la prueba de que el diseño es aditivo, no un reemplazo.
     */
    public function test_admin_sin_eventos_adicionales_se_comporta_igual_que_antes(): void
    {
        $principal = Evento::factory()->create();
        $otro = Evento::factory()->create();

        $admin = AdminUser::factory()->create(['rol' => 'admin', 'evento_id' => $principal->id]);

        $this->assertTrue($admin->tieneAccesoAEvento($principal->id));
        $this->assertFalse($admin->tieneAccesoAEvento($otro->id));
    }

    public function test_cajero_no_gana_acceso_por_eventos_adicionales(): void
    {
        // cajero no tiene UI/campo para esto, pero si alguna vez se
        // sincroniza la pivote a mano (o queda de una migración futura),
        // tieneAccesoAEvento() no debe leerla para este rol — decisión
        // explícita del usuario (28/08/2026): cajero sigue con un único
        // evento.
        $principal = Evento::factory()->create();
        $otro = Evento::factory()->create();

        $cajero = AdminUser::factory()->create(['rol' => 'cajero', 'evento_id' => $principal->id]);
        $cajero->eventosAdicionales()->sync([$otro->id]);

        $this->assertTrue($cajero->tieneAccesoAEvento($principal->id));
        $this->assertFalse($cajero->tieneAccesoAEvento($otro->id));
    }
}

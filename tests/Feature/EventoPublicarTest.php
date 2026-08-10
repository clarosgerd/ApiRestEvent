<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\Evento;
use App\Models\EventoNotification;
use App\Models\Registration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * PATCH /event/{event}/publicar y /despublicar — App\Actions\PublicarEventoAction
 * y App\Actions\DespublicarEventoAction. No existía ningún test automatizado
 * para estos 2 endpoints antes de esta suite (verificado antes de
 * escribirla) — solo se habían probado a mano con un evento descartable.
 */
class EventoPublicarTest extends TestCase
{
    use RefreshDatabase;

    public function test_publicar_marks_event_as_published_and_logs_audit(): void
    {
        Mail::fake();
        $this->actingAsAdmin();
        $evento = Evento::factory()->create(['publicado' => false]);

        $this->patchJson("/api/v1/event/{$evento->id}/publicar")
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertTrue((bool) $evento->fresh()->publicado);
        $this->assertDatabaseHas('admin_audit_logs', [
            'accion' => 'publicar',
            'entidad' => 'evento',
            'entidad_id' => $evento->id,
        ]);
        $this->assertSame(
            1,
            EventoNotification::where('evento_id', $evento->id)
                ->where('tipo', 'evento_publicado_dashboard_organizador')
                ->count()
        );
    }

    public function test_publicar_twice_returns_422(): void
    {
        Mail::fake();
        $this->actingAsAdmin();
        $evento = Evento::factory()->create(['publicado' => false]);

        $this->patchJson("/api/v1/event/{$evento->id}/publicar")->assertStatus(200);

        $this->patchJson("/api/v1/event/{$evento->id}/publicar")
            ->assertStatus(422)
            ->assertJson(['success' => false]);

        // No se duplica el envío/auditoría en el segundo intento fallido.
        $this->assertSame(
            1,
            EventoNotification::where('evento_id', $evento->id)->count()
        );
    }

    public function test_despublicar_reverts_event_to_draft(): void
    {
        $this->actingAsAdmin();
        $evento = Evento::factory()->create(['publicado' => true]);

        $this->patchJson("/api/v1/event/{$evento->id}/despublicar")
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertFalse((bool) $evento->fresh()->publicado);
        $this->assertDatabaseHas('admin_audit_logs', [
            'accion' => 'despublicar',
            'entidad' => 'evento',
            'entidad_id' => $evento->id,
        ]);
    }

    public function test_despublicar_when_not_published_returns_422(): void
    {
        $this->actingAsAdmin();
        $evento = Evento::factory()->create(['publicado' => false]);

        $this->patchJson("/api/v1/event/{$evento->id}/despublicar")
            ->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    public function test_despublicar_blocked_when_event_has_registrations(): void
    {
        $this->actingAsAdmin();
        $evento = Evento::factory()->create(['publicado' => true]);
        $formType = \App\Models\FormType::factory()->create(['event_id' => $evento->id]);
        Registration::create([
            'referencia'    => 'REF-DESPUB-TEST',
            'fecha'         => now(),
            'evento_id'     => $evento->id,
            'form_types_id' => $formType->id,
            'evento_nombre' => $evento->nombre,
            'tipo_pago'     => 'qr',
            'pago_status'   => 'paid',
        ]);

        $this->patchJson("/api/v1/event/{$evento->id}/despublicar")
            ->assertStatus(409)
            ->assertJson(['success' => false]);

        // El chequeo de 409 queda deliberadamente en el controller, no en
        // DespublicarEventoAction (ver docblock de la Action) — este test
        // también cubre esa costura.
        $this->assertTrue((bool) $evento->fresh()->publicado);
    }
}

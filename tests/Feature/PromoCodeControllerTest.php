<?php

namespace Tests\Feature;

use App\Models\Ciudad;
use App\Models\Evento;
use App\Models\Organizador;
use App\Models\Pais;
use App\Models\PromoCode;
use App\Models\SubtipoEvento;
use App\Models\TipoEvento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CRUD de PromoCodeController (store/update/destroy) — hasta el 13/09/2026
 * no tenía ningún test dedicado (solo el endpoint público de validación,
 * PromoCodeTest, y el reporte, PromoCodeReporteTest, tenían cobertura).
 * Se crea junto con multi-uso porque `destroy()` cambia de guardia
 * (`usado` → `times_used`) y `store()`/`update()` ganan el campo nuevo
 * `max_uses`.
 */
class PromoCodeControllerTest extends TestCase
{
    use RefreshDatabase;

    private Evento $evento;

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

        $this->actingAsAdmin();
    }

    public function test_default_max_uses_is_one_when_max_uses_omitted_on_store(): void
    {
        $this->postJson('/api/v1/promo-code', [
            'event_id' => $this->evento->id,
            'promo_code' => 'SINCAMPO',
            'price' => 50,
        ])->assertCreated()->assertJsonPath('promoCode.max_uses', 1);

        $this->assertDatabaseHas('promo_codes', ['promo_code' => 'SINCAMPO', 'max_uses' => 1]);
    }

    public function test_store_promo_code_respeta_max_uses_explicito(): void
    {
        $this->postJson('/api/v1/promo-code', [
            'event_id' => $this->evento->id,
            'promo_code' => 'CONCAMPO',
            'price' => 50,
            'max_uses' => 7,
        ])->assertCreated()->assertJsonPath('promoCode.max_uses', 7);
    }

    public function test_store_promo_code_rejects_max_uses_below_one(): void
    {
        $this->postJson('/api/v1/promo-code', [
            'event_id' => $this->evento->id,
            'promo_code' => 'INVALIDO',
            'price' => 50,
            'max_uses' => 0,
        ])->assertUnprocessable();

        $this->assertDatabaseCount('promo_codes', 0);
    }

    /**
     * Simula el hueco real de rollout: un `admin-eventos` viejo (antes de
     * su propio deploy) manda un PUT sin `max_uses` — no debe resetear el
     * valor que el código ya tenía.
     */
    public function test_update_promo_code_omitting_max_uses_does_not_reset_existing_value(): void
    {
        $promo = PromoCode::factory()->create([
            'event_id' => $this->evento->id, 'promo_code' => 'YAMULTI', 'max_uses' => 5,
        ]);

        $this->putJson("/api/v1/promo-code/{$promo->id}", [
            'price' => 99,
        ])->assertOk();

        $this->assertDatabaseHas('promo_codes', ['promo_code' => 'YAMULTI', 'max_uses' => 5, 'price' => 99]);
    }

    public function test_update_promo_code_puede_cambiar_max_uses_explicitamente(): void
    {
        $promo = PromoCode::factory()->create([
            'event_id' => $this->evento->id, 'promo_code' => 'CAMBIAR', 'max_uses' => 1,
        ]);

        $this->putJson("/api/v1/promo-code/{$promo->id}", [
            'max_uses' => 10,
        ])->assertOk()->assertJsonPath('promoCode.max_uses', 10);
    }

    /**
     * Multi-uso (13/09/2026) — antes el guardia era `usado || registration_id`;
     * `usado` ahora significa "agotado", así que ese guardia viejo dejaría
     * borrar un código PARCIALMENTE usado. Confirma que el guardia nuevo
     * (`times_used > 0`) sigue bloqueando aunque el código no esté agotado.
     */
    public function test_destroy_promo_code_blocked_when_times_used_greater_than_zero_even_if_not_exhausted(): void
    {
        $promo = PromoCode::factory()->create([
            'event_id' => $this->evento->id, 'promo_code' => 'PARCIAL',
            'max_uses' => 5, 'times_used' => 2, 'usado' => false,
        ]);

        $this->deleteJson("/api/v1/promo-code/{$promo->id}")
            ->assertStatus(409)
            ->assertJsonPath('error', 'No se puede eliminar este código de promoción: ya fue utilizado.');

        $this->assertDatabaseHas('promo_codes', ['id' => $promo->id]);
    }

    public function test_destroy_promo_code_sin_uso_permite_borrar(): void
    {
        $promo = PromoCode::factory()->create([
            'event_id' => $this->evento->id, 'promo_code' => 'SINUSO', 'times_used' => 0,
        ]);

        $this->deleteJson("/api/v1/promo-code/{$promo->id}")->assertOk();

        $this->assertDatabaseMissing('promo_codes', ['id' => $promo->id]);
    }
}

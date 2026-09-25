<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\AdminUser;
use App\Models\LeadCapturado;
use App\Models\Sorteo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ArmaExpositores;
use Tests\TestCase;

/**
 * SmartStand fase 4 — pasaporte médico: sorteo general del organizador entre
 * los asistentes visitados por al menos N stands distintos.
 */
class PasaporteMedicoTest extends TestCase
{
    use RefreshDatabase, ArmaExpositores;

    private function visitar($asistente, array $cuentas): void
    {
        foreach ($cuentas as $cuenta) {
            LeadCapturado::create([
                'empresa_expositora_id' => $cuenta->id,
                'participante_id'       => $asistente->id,
                'capturado_at'          => now(),
            ]);
        }
    }

    private function cuentas($evento, int $n): array
    {
        return array_map(fn () => $this->crearCuenta($evento), range(1, $n));
    }

    private function como(AdminUser $admin): void
    {
        $this->app['auth']->forgetGuards();
        $this->actingAsAdmin($admin);
    }

    public function test_show_cuenta_elegibles_por_stands_distintos_con_default_5(): void
    {
        $evento = $this->crearEvento();
        $c = $this->cuentas($evento, 6);
        $cinco = $this->crearAsistente($evento);
        $cuatro = $this->crearAsistente($evento);
        $this->visitar($cinco, array_slice($c, 0, 5));
        $this->visitar($cuatro, array_slice($c, 0, 4));
        $this->actingAsAdmin();

        $r = $this->getJson("/api/v1/event/{$evento->id}/pasaporte");

        $r->assertOk()
            ->assertJsonPath('data.minStands', 5)
            ->assertJsonPath('data.empresasTotal', 6)
            ->assertJsonPath('data.asistentesConLeads', 2)
            ->assertJsonPath('data.elegibles', 1)
            ->assertJsonPath('data.distribucion.0.stands', 4)
            ->assertJsonPath('data.distribucion.1.stands', 5);
    }

    public function test_una_sola_empresa_no_alcanza_aunque_se_intente_capturar_dos_veces(): void
    {
        $evento = $this->crearEvento();
        $evento->update(['expositores_config' => ['pasaporte_min_stands' => 2]]);
        [$a] = $this->cuentas($evento, 2);
        $asistente = $this->crearAsistente($evento);
        $this->visitar($asistente, [$a]);
        // UNIQUE (empresa, participante): el segundo alta no puede duplicar la visita.
        try {
            $this->visitar($asistente, [$a]);
        } catch (\Throwable) {
        }
        $this->actingAsAdmin();

        $this->getJson("/api/v1/event/{$evento->id}/pasaporte")
            ->assertJsonPath('data.minStands', 2)
            ->assertJsonPath('data.elegibles', 0);
    }

    public function test_sortear_elige_solo_entre_elegibles_audita_y_excluye_ganadores_previos(): void
    {
        $evento = $this->crearEvento();
        $evento->update(['expositores_config' => ['pasaporte_min_stands' => 2]]);
        $c = $this->cuentas($evento, 3);
        $ok1 = $this->crearAsistente($evento);
        $ok2 = $this->crearAsistente($evento);
        $no = $this->crearAsistente($evento);
        $this->visitar($ok1, [$c[0], $c[1]]);
        $this->visitar($ok2, [$c[0], $c[1], $c[2]]);
        $this->visitar($no, [$c[0]]);
        $admin = $this->actingAsAdmin();

        $this->postJson("/api/v1/event/{$evento->id}/pasaporte/sorteo", ['premio' => 'Viaje'])
            ->assertCreated()->assertJsonPath('sorteo.candidatosCount', 2);
        $this->postJson("/api/v1/event/{$evento->id}/pasaporte/sorteo", ['premio' => 'Reloj'])
            ->assertCreated()->assertJsonPath('sorteo.candidatosCount', 1);
        $this->postJson("/api/v1/event/{$evento->id}/pasaporte/sorteo", ['premio' => 'Nada'])
            ->assertStatus(422);

        $ganadores = Sorteo::where('tipo', Sorteo::TIPO_PASAPORTE)->pluck('participante_ganador_id')->all();
        $this->assertEqualsCanonicalizing([$ok1->id, $ok2->id], $ganadores);
        $this->assertNull(Sorteo::first()->empresa_expositora_id);

        $log = AdminAuditLog::where('accion', 'sorteo_pasaporte')->first();
        $this->assertNotNull($log);
        $this->assertSame($evento->id, (int) $log->evento_id);
        $this->assertSame($admin->id, (int) $log->admin_user_id);
    }

    public function test_min_stands_del_request_pisa_al_de_la_config(): void
    {
        $evento = $this->crearEvento();
        $c = $this->cuentas($evento, 2);
        $this->visitar($this->crearAsistente($evento), $c);
        $this->actingAsAdmin();

        $this->postJson("/api/v1/event/{$evento->id}/pasaporte/sorteo", ['premio' => 'P'])->assertStatus(422);
        $this->postJson("/api/v1/event/{$evento->id}/pasaporte/sorteo", ['premio' => 'P', 'min_stands' => 2])->assertCreated();
        $this->postJson("/api/v1/event/{$evento->id}/pasaporte/sorteo", ['premio' => 'P', 'min_stands' => 99])->assertStatus(422);
    }

    public function test_ignora_leads_de_otros_eventos(): void
    {
        $evento = $this->crearEvento();
        $otro = $this->crearEvento();
        $evento->update(['expositores_config' => ['pasaporte_min_stands' => 2]]);
        $asistente = $this->crearAsistente($evento);
        $this->visitar($asistente, [$this->crearCuenta($evento), $this->crearCuenta($otro)]);
        $this->actingAsAdmin();

        $this->getJson("/api/v1/event/{$evento->id}/pasaporte")->assertJsonPath('data.elegibles', 0);
    }

    public function test_admin_de_otro_evento_403_y_sin_login_401(): void
    {
        $evento = $this->crearEvento();
        $otro = $this->crearEvento();

        $this->getJson("/api/v1/event/{$evento->id}/pasaporte")->assertUnauthorized();

        $this->como(AdminUser::factory()->scopedTo($otro->id)->create());
        $this->getJson("/api/v1/event/{$evento->id}/pasaporte")->assertForbidden();
        $this->postJson("/api/v1/event/{$evento->id}/pasaporte/sorteo", ['premio' => 'P'])->assertForbidden();
    }
}

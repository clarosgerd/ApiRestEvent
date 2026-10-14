<?php

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use App\Models\AgendaItem;
use App\Models\Evento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Calendario del evento (05/10/2026), capa Admin\* — gap pre-existente al
 * corte del 07/10, portado junto al Lote 8 (combos/períodos de talleres)
 * con aprobación explícita del usuario. Equivalente in-process al
 * CalendarioEventoTest de admin-eventos.
 */
class CalendarioEventoControllerTest extends TestCase
{
    use RefreshDatabase;

    private array $superSession;

    private Evento $evento;

    protected function setUp(): void
    {
        parent::setUp();

        $super = AdminUser::create([
            'nombre' => 'Super', 'email' => 'super@example.net',
            'password' => Hash::make('secret123'), 'rol' => 'super_admin',
            'activo' => true, 'evento_id' => null,
        ]);
        $this->superSession = [
            'admin_token' => $super->createToken('t')->plainTextToken,
            'admin_user' => ['id' => $super->id, 'rol' => 'super_admin'],
        ];

        $this->evento = Evento::factory()->create(['fecha_inicio' => '2026-11-10', 'fecha_fin' => '2026-11-12']);
    }

    public function test_muestra_la_vista_mes_por_default_con_los_bloques_de_agenda(): void
    {
        AgendaItem::create([
            'event_id' => $this->evento->id, 'fecha' => '2026-11-11', 'hora_inicio' => '09:00:00', 'hora_fin' => '10:00:00',
            'titulo' => 'Charla inaugural', 'sala' => 'Sala A', 'ponente' => 'Dr. X', 'orden' => 1,
        ]);

        $response = $this->withSession($this->superSession)->get("/admin/eventos/{$this->evento->id}/calendario");

        $response->assertOk();
        $response->assertSee('Charla inaugural');
    }

    public function test_vista_dia_lista_los_bloques_de_la_fecha_pedida(): void
    {
        AgendaItem::create([
            'event_id' => $this->evento->id, 'fecha' => '2026-11-11', 'hora_inicio' => '09:00:00', 'hora_fin' => '10:00:00',
            'titulo' => 'Sesión del día', 'sala' => 'Sala A', 'ponente' => 'Dr. X', 'orden' => 1,
        ]);

        $response = $this->withSession($this->superSession)->get("/admin/eventos/{$this->evento->id}/calendario?vista=dia&fecha=2026-11-11");

        $response->assertOk();
        $response->assertSee('Sesión del día');
    }

    public function test_evento_sin_agenda_no_rompe_la_pantalla(): void
    {
        $response = $this->withSession($this->superSession)->get("/admin/eventos/{$this->evento->id}/calendario");

        $response->assertOk();
        $response->assertSee('todavía no tiene agenda');
    }
}

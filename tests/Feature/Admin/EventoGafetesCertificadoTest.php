<?php

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use App\Models\Evento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Gafetes/certificados parametrizables por evento (13/09/2026) + interruptor
 * de certificado automático de asistencia (07/10/2026), capa Admin\*
 * (consolidación monolito) — equivalente in-process al
 * EventoUpdateGafetesCertificadoTest de admin-eventos (que mockea Http::fake
 * porque ahí EventoController::update() es un proxy HTTP; acá
 * Admin\EventoController llama directo a ApiEventoController, así que se
 * verifica el efecto real en BD en vez de interceptar el payload saliente).
 */
class EventoGafetesCertificadoTest extends TestCase
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

        $this->evento = Evento::factory()->create([
            'certificado_solo_nombre' => false,
            'gafete_config' => null,
        ]);
    }

    public function test_update_persiste_certificado_solo_nombre_y_gafete_config_completo(): void
    {
        $response = $this->withSession($this->superSession)->put("/admin/eventos/{$this->evento->id}", [
            'name' => $this->evento->nombre,
            'certificadoSoloNombre' => '1',
            'gafeteWidthCm' => '8',
            'gafeteHeightCm' => '6',
            'gafetePerRow' => '2',
            'gafetePaper' => 'letter',
            'gafeteOrientation' => 'portrait',
        ]);

        $response->assertRedirect(route('admin.eventos.edit', $this->evento).'#datos');

        $this->evento->refresh();
        $this->assertTrue((bool) $this->evento->certificado_solo_nombre);
        $this->assertSame(8.0, (float) $this->evento->gafete_config['width_cm']);
        $this->assertSame(2, (int) $this->evento->gafete_config['per_row']);
        $this->assertSame('letter', $this->evento->gafete_config['paper']);
        $this->assertSame('portrait', $this->evento->gafete_config['orientation']);
    }

    /** Gafete tipo "pegatina" (23/09/2026) — gafeteTipo=label persiste. */
    public function test_update_persiste_gafete_config_tipo_label(): void
    {
        $this->withSession($this->superSession)->put("/admin/eventos/{$this->evento->id}", [
            'name' => $this->evento->nombre,
            'gafeteTipo' => 'label',
            'gafeteWidthCm' => '3',
            'gafeteHeightCm' => '3',
        ]);

        $this->assertSame('label', $this->evento->refresh()->gafete_config['tipo']);
    }

    /**
     * Checkbox destildeado (no viaja en el POST) y ancho de gafete vacío —
     * ambos deben persistir como false/null, no quedar ausentes (si no, el
     * checkbox nunca se podría "apagar" una vez prendido).
     */
    public function test_update_sin_checkbox_ni_ancho_guarda_false_y_null(): void
    {
        $this->evento->update(['certificado_solo_nombre' => true, 'gafete_config' => ['width_cm' => 9]]);

        $this->withSession($this->superSession)->put("/admin/eventos/{$this->evento->id}", [
            'name' => $this->evento->nombre,
        ]);

        $this->evento->refresh();
        $this->assertFalse((bool) $this->evento->certificado_solo_nombre);
        $this->assertNull($this->evento->gafete_config);
    }

    /**
     * Interruptor por evento del certificado automático de asistencia a
     * sesiones (07/10/2026) — checkbox real en update(): se manda siempre
     * (igual que certificadoSoloNombre) para que destildearlo también
     * persista.
     */
    public function test_update_persiste_certificado_asistencia_activo_tildado_y_destildado(): void
    {
        $this->assertTrue((bool) $this->evento->refresh()->certificado_asistencia_activo);

        $this->withSession($this->superSession)->put("/admin/eventos/{$this->evento->id}", [
            'name' => $this->evento->nombre,
            'certificadoAsistenciaActivo' => '1',
        ]);
        $this->assertTrue((bool) $this->evento->refresh()->certificado_asistencia_activo);

        $this->withSession($this->superSession)->put("/admin/eventos/{$this->evento->id}", [
            'name' => $this->evento->nombre,
        ]);
        $this->assertFalse((bool) $this->evento->refresh()->certificado_asistencia_activo);
    }

    /**
     * Smoke test de renderizado real — confirma que la sección nueva no
     * rompe el resto de la pantalla de edición.
     */
    public function test_edit_renderiza_el_bloque_de_gafetes_y_certificados(): void
    {
        $response = $this->withSession($this->superSession)->get("/admin/eventos/{$this->evento->id}/edit");

        $response->assertOk();
        $response->assertSee('Certificado solo con nombre');
        $response->assertSee('Enviar certificado automático de asistencia a sesiones');
        $response->assertSee('Tamaño de gafete');
    }
}

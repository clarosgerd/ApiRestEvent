<?php

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use App\Models\Evento;
use App\Models\FormType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Bug real confirmado 15/10/2026 (reportado por el usuario): en un evento
 * tipo congreso con un form_type "stand" (empresa expositora) que oculta
 * el campo `alias` (reusado como "Título" en congresos, ver
 * toggleAliasTituloMode() en admin/caja/_formulario.blade.php), el botón
 * de Caja no dejaba avanzar sin ningún mensaje visible.
 *
 * Causa: toggleAliasTituloMode() marcaba `select.required = true` para
 * CUALQUIER form_type tipo='congreso', sin mirar camposOcultos — corría
 * DESPUÉS de applyCamposOcultos() en actualizarSecciones(), así que el
 * campo quedaba oculto (display:none) pero igual required=true. Un campo
 * required oculto bloquea el submit del navegador en silencio. Mismo fix
 * portado en elascenso/admin-eventos (ver CajaTituloCongresoCamposOcultosTest
 * de ese repo, misma causa/fix, HTTP-fake en vez de modelos reales).
 */
class CajaTituloCongresoCamposOcultosTest extends TestCase
{
    use RefreshDatabase;

    public function test_titulo_nunca_queda_required_si_alias_esta_en_campos_ocultos(): void
    {
        $evento = Evento::factory()->create();
        FormType::factory()->create([
            'event_id' => $evento->id,
            'name' => 'Stand',
            'tipo' => 'congreso',
            'requiere_categoria' => true,
            'campos_ocultos' => ['alias'],
        ]);

        $super = AdminUser::create([
            'nombre' => 'Super', 'email' => 'super@example.net',
            'password' => Hash::make('secret123'), 'rol' => 'super_admin',
            'activo' => true, 'evento_id' => null,
        ]);
        $session = [
            'admin_token' => $super->createToken('t')->plainTextToken,
            'admin_user' => ['id' => $super->id, 'rol' => 'super_admin'],
        ];

        $html = $this->withSession($session)->get("/admin/eventos/{$evento->id}/caja/nueva")
            ->assertOk()->getContent();

        // El fix: el required del Título respeta camposOcultos.
        $this->assertStringContainsString('select.required = isCongreso && !oculto;', $html);
        // El bug original: required solo dependía de isCongreso.
        $this->assertStringNotContainsString('select.required = isCongreso;', $html);
    }
}

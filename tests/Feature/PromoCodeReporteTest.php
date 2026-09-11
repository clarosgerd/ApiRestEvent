<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Category;
use App\Models\Ciudad;
use App\Models\Evento;
use App\Models\FormType;
use App\Models\Organizador;
use App\Models\Pais;
use App\Models\Participante;
use App\Models\PromoCode;
use App\Models\Registration;
use App\Models\SubtipoEvento;
use App\Models\TipoEvento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reporte de códigos promocionales usados, por evento (11/09/2026) — ver
 * PromoCodeReporteData. El usuario preguntó si existía algo así al generar
 * códigos reales para Naranjillo Ultra Trail; no existía.
 */
class PromoCodeReporteTest extends TestCase
{
    use RefreshDatabase;

    private function crearEvento(): Evento
    {
        $pais = Pais::factory()->create();
        $ciudad = Ciudad::factory()->create(['pais_id' => $pais->id]);
        $organizador = Organizador::factory()->create();
        $tipoEvento = TipoEvento::factory()->create();
        $subtipoEvento = SubtipoEvento::factory()->create(['tipo_evento_id' => $tipoEvento->id]);

        return Evento::factory()->create([
            'organizador_id' => $organizador->id,
            'tipo_evento_id' => $tipoEvento->id,
            'subtipo_evento_id' => $subtipoEvento->id,
            'pais_id' => $pais->id,
            'ciudad_id' => $ciudad->id,
        ]);
    }

    private function crearParticipanteConPromo(Evento $evento, string $promoCodigo, float $promoDescuento): Participante
    {
        $formType = FormType::factory()->create(['event_id' => $evento->id]);
        $categoria = Category::factory()->create(['event_id' => $evento->id, 'price' => 100]);

        $registration = Registration::create([
            'referencia' => 'REF' . rand(100000, 999999),
            'fecha' => now(),
            'evento_id' => $evento->id,
            'form_types_id' => $formType->id,
            'evento_nombre' => $evento->nombre,
            'tipo_pago' => 'sip',
            'pago_status' => 'paid',
        ]);

        return Participante::create([
            'registration_id' => $registration->id,
            'nombre' => 'Nombre' . rand(1000, 9999), 'apellido' => 'Apellido',
            'genero' => 'Femenino', 'tipo_documento' => 'DNI',
            'numero_documento' => (string) rand(1000000, 9999999),
            'fecha_nacimiento' => '1990-01-01', 'edad' => 30,
            'correo' => 'test' . rand(1000, 9999) . '@test.net', 'direccion' => 'x', 'ciudad' => 'x', 'telefono' => '123',
            'categoria' => $categoria->id, 'precio_categoria' => 100, 'subtotal' => 90,
            'promo_codigo' => $promoCodigo, 'promo_descuento' => $promoDescuento,
        ]);
    }

    public function test_codigo_sin_usar_no_tiene_participante(): void
    {
        $evento = $this->crearEvento();
        PromoCode::factory()->create([
            'event_id' => $evento->id, 'promo_code' => 'SINUSAR', 'discount_type' => 'percentage',
            'discount_percent' => 0.10, 'usado' => false,
        ]);

        $this->actingAsAdmin();

        $response = $this->getJson("/api/v1/event/{$evento->id}/promo-codes-reporte");

        $response->assertOk()
            ->assertJsonPath('filas.0.codigo', 'SINUSAR')
            ->assertJsonPath('filas.0.usado', false)
            ->assertJsonPath('filas.0.participante', null)
            ->assertJsonPath('filas.0.montoDescontado', null);
    }

    public function test_codigo_usado_muestra_participante_y_monto(): void
    {
        $evento = $this->crearEvento();
        PromoCode::factory()->create([
            'event_id' => $evento->id, 'promo_code' => 'USADO10', 'discount_type' => 'percentage',
            'discount_percent' => 0.10, 'usado' => true,
        ]);
        $participante = $this->crearParticipanteConPromo($evento, 'USADO10', 10.0);

        $this->actingAsAdmin();

        $response = $this->getJson("/api/v1/event/{$evento->id}/promo-codes-reporte");

        $response->assertOk()
            ->assertJsonPath('filas.0.usado', true)
            ->assertJsonPath('filas.0.participante.nombre', $participante->nombre)
            ->assertJsonPath('filas.0.participante.numeroDocumento', $participante->numero_documento)
            ->assertJsonPath('filas.0.montoDescontado', 10);
    }

    public function test_totales_correctos(): void
    {
        $evento = $this->crearEvento();
        PromoCode::factory()->create(['event_id' => $evento->id, 'promo_code' => 'A', 'usado' => true]);
        PromoCode::factory()->create(['event_id' => $evento->id, 'promo_code' => 'B', 'usado' => false]);
        PromoCode::factory()->create(['event_id' => $evento->id, 'promo_code' => 'C', 'usado' => true]);
        $this->crearParticipanteConPromo($evento, 'A', 15.0);
        $this->crearParticipanteConPromo($evento, 'C', 25.0);

        $this->actingAsAdmin();

        $response = $this->getJson("/api/v1/event/{$evento->id}/promo-codes-reporte");

        $response->assertOk()
            ->assertJsonPath('totalCodigos', 3)
            ->assertJsonPath('totalUsados', 2)
            ->assertJsonPath('totalDescontado', 40);
    }

    public function test_codigos_de_otro_evento_no_aparecen(): void
    {
        // Relevante porque promo_code es único a nivel GLOBAL, no por
        // evento — confirma que el filtro por event_id realmente excluye
        // códigos de otros eventos, no solo que no colisionen por nombre.
        $evento = $this->crearEvento();
        $otroEvento = $this->crearEvento();
        PromoCode::factory()->create(['event_id' => $evento->id, 'promo_code' => 'DELEVENTO']);
        PromoCode::factory()->create(['event_id' => $otroEvento->id, 'promo_code' => 'DELOTRO']);

        $this->actingAsAdmin();

        $response = $this->getJson("/api/v1/event/{$evento->id}/promo-codes-reporte");

        $response->assertOk()->assertJsonCount(1, 'filas')
            ->assertJsonPath('filas.0.codigo', 'DELEVENTO');
    }

    public function test_admin_de_otro_evento_no_puede_ver_el_reporte(): void
    {
        $evento = $this->crearEvento();
        $otroEvento = $this->crearEvento();
        $admin = AdminUser::factory()->scopedTo($otroEvento->id)->create();
        $this->actingAsAdmin($admin);

        $response = $this->getJson("/api/v1/event/{$evento->id}/promo-codes-reporte");

        $response->assertStatus(403);
    }
}

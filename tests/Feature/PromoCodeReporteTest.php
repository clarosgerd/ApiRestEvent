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
use App\Models\PromoCodeUsage;
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

    /**
     * Crea un Participante con un promo aplicado Y la fila de
     * PromoCodeUsage correspondiente (13/09/2026, multi-uso) — el reporte
     * ahora lee de `promo_code_usages`, no de un match por texto contra
     * `participantes.promo_codigo`; sin esta fila el uso no aparecería en
     * el reporte aunque el Participante sí tenga el código guardado.
     */
    private function crearParticipanteConPromo(Evento $evento, PromoCode $promoCode, float $promoDescuento): Participante
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

        $participante = Participante::create([
            'registration_id' => $registration->id,
            'nombre' => 'Nombre' . rand(1000, 9999), 'apellido' => 'Apellido',
            'genero' => 'Femenino', 'tipo_documento' => 'DNI',
            'numero_documento' => (string) rand(1000000, 9999999),
            'fecha_nacimiento' => '1990-01-01', 'edad' => 30,
            'correo' => 'test' . rand(1000, 9999) . '@test.net', 'direccion' => 'x', 'ciudad' => 'x', 'telefono' => '123',
            'categoria' => $categoria->id, 'precio_categoria' => 100, 'subtotal' => 90,
            'promo_codigo' => $promoCode->promo_code, 'promo_descuento' => $promoDescuento,
        ]);

        PromoCodeUsage::create([
            'promo_code_id' => $promoCode->id,
            'registration_id' => $registration->id,
            'participante_id' => $participante->id,
            'monto_descontado' => $promoDescuento,
            'used_at' => now(),
        ]);

        return $participante;
    }

    public function test_codigo_sin_usar_tiene_lista_de_usos_vacia(): void
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
            ->assertJsonPath('filas.0.vecesUsado', 0)
            ->assertJsonCount(0, 'filas.0.usos');
    }

    public function test_codigo_usado_una_vez_muestra_un_uso_con_participante_y_monto(): void
    {
        $evento = $this->crearEvento();
        $promo = PromoCode::factory()->create([
            'event_id' => $evento->id, 'promo_code' => 'USADO10', 'discount_type' => 'percentage',
            'discount_percent' => 0.10, 'usado' => true, 'times_used' => 1,
        ]);
        $participante = $this->crearParticipanteConPromo($evento, $promo, 10.0);

        $this->actingAsAdmin();

        $response = $this->getJson("/api/v1/event/{$evento->id}/promo-codes-reporte");

        $response->assertOk()
            ->assertJsonPath('filas.0.usado', true)
            ->assertJsonCount(1, 'filas.0.usos')
            ->assertJsonPath('filas.0.usos.0.participante.nombre', $participante->nombre)
            ->assertJsonPath('filas.0.usos.0.participante.numeroDocumento', $participante->numero_documento)
            ->assertJsonPath('filas.0.usos.0.montoDescontado', 10);
    }

    /**
     * Multi-uso (13/09/2026) — un código con max_uses=3 usado por 3
     * participantes distintos muestra los 3 en `usos[]`, en orden de
     * `used_at`.
     */
    public function test_codigo_multi_uso_muestra_todas_las_participaciones(): void
    {
        $evento = $this->crearEvento();
        $promo = PromoCode::factory()->create([
            'event_id' => $evento->id, 'promo_code' => 'MULTI3', 'discount_type' => 'fixed_price',
            'price' => 20, 'max_uses' => 3, 'times_used' => 3, 'usado' => true,
        ]);
        $this->crearParticipanteConPromo($evento, $promo, 5.0);
        $this->crearParticipanteConPromo($evento, $promo, 5.0);
        $this->crearParticipanteConPromo($evento, $promo, 5.0);

        $this->actingAsAdmin();

        $response = $this->getJson("/api/v1/event/{$evento->id}/promo-codes-reporte");

        $response->assertOk()
            ->assertJsonPath('filas.0.maxUsos', 3)
            ->assertJsonPath('filas.0.vecesUsado', 3)
            ->assertJsonCount(3, 'filas.0.usos');
    }

    public function test_totales_reflejan_veces_usado_y_total_usos(): void
    {
        $evento = $this->crearEvento();
        $promoA = PromoCode::factory()->create(['event_id' => $evento->id, 'promo_code' => 'A', 'usado' => true, 'times_used' => 1]);
        PromoCode::factory()->create(['event_id' => $evento->id, 'promo_code' => 'B', 'usado' => false]);
        $promoC = PromoCode::factory()->create(['event_id' => $evento->id, 'promo_code' => 'C', 'max_uses' => 2, 'times_used' => 2, 'usado' => true]);
        $this->crearParticipanteConPromo($evento, $promoA, 15.0);
        $this->crearParticipanteConPromo($evento, $promoC, 25.0);
        $this->crearParticipanteConPromo($evento, $promoC, 25.0);

        $this->actingAsAdmin();

        $response = $this->getJson("/api/v1/event/{$evento->id}/promo-codes-reporte");

        $response->assertOk()
            ->assertJsonPath('totalCodigos', 3)
            ->assertJsonPath('totalUsados', 2)   // códigos tocados al menos una vez: A y C
            ->assertJsonPath('totalUsos', 3)     // usage-events totales: 1 (A) + 2 (C)
            ->assertJsonPath('totalDescontado', 65);
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

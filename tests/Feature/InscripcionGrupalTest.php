<?php

namespace Tests\Feature;

use App\Actions\ActualizarInscripcionAction;
use App\Models\Category;
use App\Models\Evento;
use App\Models\FormType;
use App\Models\Registration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Inscripción grupal por tipo de formulario (26/09/2026): con `permite_inscripcion_grupal`
 * y N = `max_integrantes_grupo`, más de N participantes se rechaza y el descuento de grupo
 * (`descuento_registrante_pct` sobre la inscripción) solo existe al llegar a N. Antes solo lo
 * aplicaban el front y el proxy; ApiRestEvent aceptaba cualquier cantidad y cualquier descuento.
 */
class InscripcionGrupalTest extends TestCase
{
    use RefreshDatabase;

    private Evento $event;
    private Category $categoria;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->actingAsPersona();
        $this->event = Evento::factory()->create();
        $this->categoria = Category::factory()->create(['event_id' => $this->event->id, 'price' => 100]);
    }

    private function grupal(int $max = 3, float $pct = 0.10, array $extra = []): FormType
    {
        return FormType::factory()->create(array_merge([
            'event_id' => $this->event->id, 'cupo_total' => 100, 'activo' => true, 'requiere_categoria' => true,
            'permite_inscripcion_grupal' => true, 'max_integrantes_grupo' => $max, 'descuento_registrante_pct' => $pct,
            'un_solo_participante' => false,
        ], $extra));
    }

    private function participantes(int $n): array
    {
        return collect(range(1, $n))->map(fn ($i) => [
            'nombre' => 'Ana', 'apellido' => 'Garcia', 'alias' => '', 'genero' => 'Femenino',
            'tipoDocumento' => 'CI', 'numeroDocumento' => (string) (10000000 + $i), 'polera' => 'M', 'precioPolera' => 0,
            'nacimiento' => ['dia' => 10, 'mes' => 5, 'anio' => 1995], 'edad' => 31,
            'correo' => "p{$i}@test.net", 'direccion' => 'x', 'ciudad' => 'x', 'telefono' => '123',
            'categoria' => $this->categoria->id, 'precioCategoria' => 100,
            'donacion' => 0, 'promoDescuento' => 0, 'promoCodigo' => '', 'subtotal' => 100,
            'contacto_emergencia' => ['nombre' => 'X', 'celular' => '123', 'relacion' => 'Padre'],
            'souvenirs' => [],
        ])->all();
    }

    private function totales(int $n, float $descuentoGrupal): array
    {
        return [
            'inscripcion' => 100 * $n, 'donacion' => 0, 'souvenirs' => 0, 'fee' => 5 * $n, 'descuento' => 0,
            'descuento_registrante' => $descuentoGrupal, 'grand_total' => 105 * $n - $descuentoGrupal,
        ];
    }

    private function payload(FormType $ft, int $n, float $descuentoGrupal = 0): array
    {
        return [[
            'referencia' => 'REF-' . Str::random(8), 'fecha' => now()->toDateTimeString(),
            'evento_id' => $this->event->id, 'form_types_id' => $ft->id, 'evento_nombre' => $this->event->nombre,
            'tipo_pago' => 'QR', 'pago_status' => 'pending',
            'totales' => $this->totales($n, $descuentoGrupal),
            'participantes' => $this->participantes($n),
        ]];
    }

    public function test_mas_participantes_que_el_maximo_da_422(): void
    {
        $ft = $this->grupal(3);

        $this->postJson('/api/v1/registrations', $this->payload($ft, 4))->assertStatus(422);
        $this->assertDatabaseCount('registrations', 0);
    }

    public function test_al_llegar_al_maximo_con_el_descuento_configurado_se_acepta(): void
    {
        $ft = $this->grupal(3, 0.10); // 10 % de 300 = 30

        $this->postJson('/api/v1/registrations', $this->payload($ft, 3, 30))->assertCreated();
        $this->assertDatabaseHas('registration_totals', ['descuento_registrante' => 30]);
    }

    public function test_un_descuento_de_grupo_inventado_mayor_al_configurado_da_422(): void
    {
        $ft = $this->grupal(3, 0.10);

        $this->postJson('/api/v1/registrations', $this->payload($ft, 3, 150))->assertStatus(422);
    }

    public function test_un_descuento_de_grupo_antes_de_llegar_al_umbral_da_422(): void
    {
        $ft = $this->grupal(3, 0.10);

        $this->postJson('/api/v1/registrations', $this->payload($ft, 2, 20))->assertStatus(422);
    }

    /** Regresión: bajo el umbral y sin descuento, una inscripción grupal se comporta como siempre. */
    public function test_bajo_el_maximo_y_sin_descuento_se_acepta(): void
    {
        $ft = $this->grupal(3, 0.10);

        $this->postJson('/api/v1/registrations', $this->payload($ft, 2))->assertCreated();
    }

    /** Descuento 0 % = "solo tope": se puede llegar a N sin descuento; con descuento se rechaza. */
    public function test_con_descuento_cero_es_solo_tope(): void
    {
        $ft = $this->grupal(3, 0.0);

        $this->postJson('/api/v1/registrations', $this->payload($ft, 3, 0))->assertCreated();
        $this->postJson('/api/v1/registrations', $this->payload($ft, 3, 5))->assertStatus(422);
    }

    /** Sin inscripción grupal: sin tope y sin descuento de grupo (como hoy en los tipos con el flag apagado). */
    public function test_sin_inscripcion_grupal_no_hay_tope_ni_descuento(): void
    {
        $ft = $this->grupal(3, 0.10, ['permite_inscripcion_grupal' => false]);

        $this->postJson('/api/v1/registrations', $this->payload($ft, 5))->assertCreated();
        $this->postJson('/api/v1/registrations', $this->payload($ft, 5, 50))->assertStatus(422);
    }

    public function test_el_flag_un_solo_participante_gana_sobre_lo_grupal(): void
    {
        $ft = $this->grupal(3, 0.10, ['un_solo_participante' => true]);

        $this->postJson('/api/v1/registrations', $this->payload($ft, 2))->assertStatus(422);
        $this->postJson('/api/v1/registrations', $this->payload($ft, 1))->assertCreated();
    }

    public function test_editar_una_pendiente_pasando_del_maximo_o_inventando_descuento_da_error(): void
    {
        $ft = $this->grupal(3, 0.10);
        $this->postJson('/api/v1/registrations', $this->payload($ft, 2))->assertCreated();
        $reference = Registration::firstOrFail()->referencia;

        try {
            app(ActualizarInscripcionAction::class)->handle($reference, [
                'participantes' => $this->participantes(4), 'totales' => $this->totales(4, 0),
            ]);
            $this->fail('Debió rechazar 4 participantes con máximo 3.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('Máximo 3', $e->getMessage());
        }

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('descuento de grupo');
        app(ActualizarInscripcionAction::class)->handle($reference, [
            'participantes' => $this->participantes(2), 'totales' => $this->totales(2, 40),
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Mail\ListaEsperaPromovidaMail;
use App\Models\Ciudad;
use App\Models\Evento;
use App\Models\FormType;
use App\Models\ItemStock;
use App\Models\ListaEspera;
use App\Models\Organizador;
use App\Models\Pais;
use App\Models\Souvenir;
use App\Models\SubtipoEvento;
use App\Models\TipoEvento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Promoción automática de lista de espera — ver
 * PromoverListaEsperaAction y PRD-kit-tallas-stock-lista-espera.md.
 * SIEMPRE Mail::fake() — este proyecto no tiene sandbox de email por
 * defecto, un test sin fake mandaría un correo real de verdad.
 */
class PromoverListaEsperaTest extends TestCase
{
    use RefreshDatabase;

    private Evento $evento;

    private FormType $formType;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

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

        $this->formType = FormType::factory()->create([
            'event_id' => $this->evento->id,
            'cupo_total' => 5,
            'activo' => false, // lleno
        ]);
    }

    private function anotar(array $overrides = []): ListaEspera
    {
        // `created_at` no está en $fillable (mass-assignment protegido a
        // propósito, ver el modelo) — se fuerza después de crear, para
        // poder simular orden FIFO en los tests sin abrir el fillable a
        // que cualquier request lo pueda pisar.
        $createdAt = $overrides['created_at'] ?? null;
        unset($overrides['created_at']);

        $entry = ListaEspera::create(array_merge([
            'evento_id' => $this->evento->id,
            'form_types_id' => $this->formType->id,
            'nombre' => 'Ana',
            'correo' => 'ana'.rand(1, 999999).'@test.net',
            'estado' => 'pendiente',
        ], $overrides));

        if ($createdAt) {
            $entry->forceFill(['created_at' => $createdAt])->save();
        }

        return $entry;
    }

    public function test_promueve_por_cupo_general_cuando_hay_hueco(): void
    {
        // cupo_total=5, 0 inscritos vigentes => cupoDisponible()=5.
        $entry = $this->anotar();

        Artisan::call('lista-espera:promover');

        Mail::assertSent(ListaEsperaPromovidaMail::class, fn ($mail) => $mail->hasTo($entry->correo));
        $this->assertDatabaseHas('lista_espera', ['id' => $entry->id, 'estado' => 'promovido']);
    }

    public function test_no_promueve_si_sigue_sin_cupo(): void
    {
        // Simula cupo realmente lleno: form_type.cupo_total=0.
        $this->formType->update(['cupo_total' => 0]);
        $entry = $this->anotar();

        Artisan::call('lista-espera:promover');

        Mail::assertNothingSent();
        $this->assertDatabaseHas('lista_espera', ['id' => $entry->id, 'estado' => 'pendiente']);
    }

    public function test_promueve_solo_hasta_el_hueco_disponible_fifo(): void
    {
        $this->formType->update(['cupo_total' => 1]); // solo 1 hueco

        $primero = $this->anotar(['created_at' => now()->subMinutes(10)]);
        $segundo = $this->anotar(['created_at' => now()->subMinutes(5)]);

        Artisan::call('lista-espera:promover');

        Mail::assertSent(ListaEsperaPromovidaMail::class, 1);
        $this->assertDatabaseHas('lista_espera', ['id' => $primero->id, 'estado' => 'promovido']);
        $this->assertDatabaseHas('lista_espera', ['id' => $segundo->id, 'estado' => 'pendiente']);
    }

    public function test_promueve_por_stock_de_item_puntual(): void
    {
        $souvenir = Souvenir::factory()->create(['form_types_id' => $this->formType->id, 'requiere_talla' => true]);
        ItemStock::create(['souvenir_id' => $souvenir->id, 'talla' => 'M', 'sexo' => null, 'cantidad_total' => 1]);
        $entry = $this->anotar(['souvenir_id' => $souvenir->id, 'talla' => 'M']);

        Artisan::call('lista-espera:promover');

        Mail::assertSent(ListaEsperaPromovidaMail::class, fn ($mail) => $mail->hasTo($entry->correo));
        $this->assertDatabaseHas('lista_espera', ['id' => $entry->id, 'estado' => 'promovido']);
    }

    public function test_un_fallo_de_envio_no_marca_promovido_para_reintentar(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP caído'));
        $entry = $this->anotar();

        Artisan::call('lista-espera:promover');

        $this->assertDatabaseHas('lista_espera', ['id' => $entry->id, 'estado' => 'pendiente']);
    }

    public function test_no_reenvia_al_correr_el_comando_dos_veces(): void
    {
        $entry = $this->anotar();

        Artisan::call('lista-espera:promover');
        Artisan::call('lista-espera:promover');

        Mail::assertSent(ListaEsperaPromovidaMail::class, 1);
    }
}

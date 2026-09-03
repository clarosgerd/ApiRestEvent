<?php

namespace Tests\Feature;

use App\Mail\PagoConfirmadoMail;
use App\Models\Category;
use App\Models\Ciudad;
use App\Models\Evento;
use App\Models\FormType;
use App\Models\Organizador;
use App\Models\Pais;
use App\Models\Participante;
use App\Models\ParticipanteTallerSesion;
use App\Models\Registration;
use App\Models\SesionCongreso;
use App\Models\SubtipoEvento;
use App\Models\Taller;
use App\Models\TipoEvento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `emails/partials/participantes.blade.php` — pedido del usuario
 * (19/08/2026) a partir del e-ticket real de LA-C404D0CA (evento de
 * congreso): "Camiseta" no debería mostrarse en congresos (no reparten
 * camiseta), y souvenirs/taller solo si el participante realmente tiene
 * algo cargado. De paso corrige un bug real encontrado al tocar este
 * archivo: la línea "Taller:" mostraba `souvenirParticipante`, nunca la
 * selección real de taller — la sesión de congreso nunca se veía en el
 * correo.
 */
class EmailParticipantesPartialTest extends TestCase
{
    use RefreshDatabase;

    private function crearEvento(string $tipoFormulario): array
    {
        $pais = Pais::factory()->create();
        $ciudad = Ciudad::factory()->create(['pais_id' => $pais->id]);
        $organizador = Organizador::factory()->create();
        $tipoEvento = TipoEvento::factory()->create();
        $subtipoEvento = SubtipoEvento::factory()->create(['tipo_evento_id' => $tipoEvento->id]);

        $evento = Evento::factory()->create([
            'organizador_id' => $organizador->id,
            'tipo_evento_id' => $tipoEvento->id,
            'subtipo_evento_id' => $subtipoEvento->id,
            'pais_id' => $pais->id,
            'ciudad_id' => $ciudad->id,
        ]);
        $formType = FormType::factory()->create(['event_id' => $evento->id, 'tipo' => $tipoFormulario]);
        $categoria = Category::factory()->create(['event_id' => $evento->id, 'price' => 100]);

        return [$evento, $formType, $categoria];
    }

    private function crearRegistration(Evento $evento, FormType $formType): Registration
    {
        $registration = Registration::create([
            'referencia' => 'REF' . rand(100000, 999999),
            'fecha' => now(),
            'evento_id' => $evento->id,
            'form_types_id' => $formType->id,
            'evento_nombre' => $evento->nombre,
            'tipo_pago' => 'QR',
            'pago_status' => 'paid',
        ]);

        // emails/partials/totales.blade.php exige `registration_totals` —
        // sin esto revienta con "Attempt to read property on null".
        \App\Models\RegistrationTotal::create([
            'registration_id' => $registration->id,
            'inscripcion' => 100, 'donacion' => 0, 'souvenirs' => 0, 'talleres' => 0,
            'fee' => 5, 'descuento' => 0, 'descuento_registrante' => 0, 'grand_total' => 105,
        ]);

        return $registration;
    }

    private function renderCorreo(Registration $registration): string
    {
        $registration->loadMissing([
            'participants.souvenirParticipante',
            'participants.talleresSesiones.taller',
            'totals', 'evento.organizador', 'formType',
        ]);

        return (new PagoConfirmadoMail($registration))->render();
    }

    public function test_congreso_oculta_camiseta_y_muestra_el_taller_real(): void
    {
        [$evento, $formType, $categoria] = $this->crearEvento('congreso');
        $registration = $this->crearRegistration($evento, $formType);

        $participante = Participante::create([
            'registration_id' => $registration->id,
            'nombre' => 'Estefany', 'apellido' => 'Centellas', 'alias' => 'Dra.', 'genero' => 'Femenino',
            'tipo_documento' => 'DNI', 'numero_documento' => '6897549',
            'fecha_nacimiento' => '1990-01-01', 'edad' => 36,
            'correo' => 'estefany@test.net', 'direccion' => 'x', 'ciudad' => 'x', 'telefono' => '123',
            'categoria' => $categoria->id, 'precio_categoria' => 100, 'subtotal' => 100,
            'polera' => 'No shirt',
        ]);

        $taller = Taller::factory()->create(['evento_id' => $evento->id, 'nombre' => 'Bombas Elastoméricas']);
        $sesion = SesionCongreso::factory()->create(['evento_id' => $evento->id, 'taller_id' => $taller->id]);
        ParticipanteTallerSesion::create([
            'participante_id' => $participante->id, 'sesion_congreso_id' => $sesion->id, 'taller_id' => $taller->id,
            'unit_price' => 800, 'discount' => 0, 'total' => 800,
        ]);

        $html = $this->renderCorreo($registration);

        $this->assertStringNotContainsString('Camiseta', $html);
        $this->assertStringContainsString('Taller: <strong>Bombas Elastoméricas (Bs800.00)</strong>', $html);
        $this->assertStringNotContainsString('Souvenirs:', $html);
    }

    public function test_deportivo_sigue_mostrando_camiseta_y_oculta_taller_sin_seleccion(): void
    {
        [$evento, $formType, $categoria] = $this->crearEvento('deportivo');
        $registration = $this->crearRegistration($evento, $formType);

        Participante::create([
            'registration_id' => $registration->id,
            'nombre' => 'Ana', 'apellido' => 'Prueba', 'alias' => 'ana', 'genero' => 'Femenino',
            'tipo_documento' => 'DNI', 'numero_documento' => '87654321',
            'fecha_nacimiento' => '1995-01-01', 'edad' => 31,
            'correo' => 'ana@test.net', 'direccion' => 'x', 'ciudad' => 'x', 'telefono' => '123',
            'categoria' => $categoria->id, 'precio_categoria' => 100, 'subtotal' => 100,
            'polera' => 'M',
        ]);

        $html = $this->renderCorreo($registration);

        $this->assertStringContainsString('Camiseta: <strong>M</strong>', $html);
        $this->assertStringNotContainsString('Taller:', $html);
        $this->assertStringNotContainsString('Souvenirs:', $html);
    }

    public function test_muestra_souvenirs_solo_cuando_hay(): void
    {
        [$evento, $formType, $categoria] = $this->crearEvento('deportivo');
        $registration = $this->crearRegistration($evento, $formType);

        $participante = Participante::create([
            'registration_id' => $registration->id,
            'nombre' => 'Ana', 'apellido' => 'Prueba', 'alias' => 'ana', 'genero' => 'Femenino',
            'tipo_documento' => 'DNI', 'numero_documento' => '11223344',
            'fecha_nacimiento' => '1995-01-01', 'edad' => 31,
            'correo' => 'ana2@test.net', 'direccion' => 'x', 'ciudad' => 'x', 'telefono' => '123',
            'categoria' => $categoria->id, 'precio_categoria' => 100, 'subtotal' => 130,
            'polera' => 'M',
        ]);

        // souvenir_id no tiene FK real (ver migración) — no hace falta un
        // Souvenir de catálogo, esta tabla es el snapshot por participante.
        \App\Models\SouvenirParticipante::create([
            'participante_id' => $participante->id, 'souvenir_id' => 1,
            'nombre' => 'Mochila', 'precio' => 30,
        ]);

        $html = $this->renderCorreo($registration);

        $this->assertStringContainsString('Souvenirs: <strong>Mochila (Bs30.00)</strong>', $html);
    }

    /**
     * Talla real de la polera (03/09/2026) — este correo mostraba
     * "Camiseta: No shirt" a cualquier participante de un evento con la
     * polera modelada como souvenir normal (`participantes.polera` es un
     * campo legacy que queda siempre en ese sentinel para esos eventos).
     * Ver App\Support\TallaPoleraData — mismo colaborador usado por el
     * Reporte de poleras del dashboard, el CSV/JSON de delivery, y el CSV
     * del organizador.
     */
    public function test_deportivo_muestra_la_talla_real_del_souvenir_marcado_es_polera(): void
    {
        [$evento, $formType, $categoria] = $this->crearEvento('deportivo');
        $registration = $this->crearRegistration($evento, $formType);

        $polera = \App\Models\Souvenir::factory()->create([
            'form_types_id' => $formType->id, 'requiere_talla' => true, 'es_polera' => true,
        ]);

        $participante = Participante::create([
            'registration_id' => $registration->id,
            'nombre' => 'Ana', 'apellido' => 'Prueba', 'alias' => 'ana', 'genero' => 'Femenino',
            'tipo_documento' => 'DNI', 'numero_documento' => '99887766',
            'fecha_nacimiento' => '1995-01-01', 'edad' => 31,
            'correo' => 'ana3@test.net', 'direccion' => 'x', 'ciudad' => 'x', 'telefono' => '123',
            'categoria' => $categoria->id, 'precio_categoria' => 100, 'subtotal' => 100,
            // Sentinel legacy — el fix no debe leer esto cuando hay un
            // souvenir es_polera marcado.
            'polera' => 'No shirt',
        ]);
        \App\Models\SouvenirParticipante::create([
            'participante_id' => $participante->id, 'souvenir_id' => $polera->id,
            'nombre' => $polera->name, 'precio' => $polera->price, 'talla' => 'XL',
        ]);

        $html = $this->renderCorreo($registration);

        $this->assertStringContainsString('Camiseta: <strong>XL</strong>', $html);
        $this->assertStringNotContainsString('Camiseta: <strong>No shirt</strong>', $html);
    }
}

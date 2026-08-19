<?php

namespace Database\Seeders;

use App\Models\Ciudad;
use App\Models\Evento;
use App\Models\FormType;
use App\Models\Organizador;
use App\Models\Pais;
use App\Models\SesionCongreso;
use App\Models\SubtipoEvento;
use App\Models\Taller;
use App\Models\TipoEvento;
use Illuminate\Database\Seeder;

/**
 * Evento demo para probar de punta a punta, desde el navegador (elascenso/event,
 * inscripción pública), el flujo de selección de talleres agregado el
 * 18/08/2026 — ver brain/PLAN-CONGRESOS-TALLERES-HORARIOS-IMPLEMENTACION.md.
 *
 * Distinto de DemoCongresoSeeder (ese crea un evento CERRADO con
 * inscripciones ya pagadas, pensado para probar check-in/certificados/
 * presupuesto desde el panel admin). Este crea un evento ABIERTO,
 * publicado, sin categorías (form_type.requiere_categoria=false, precio
 * fijo) para que una inscripción nueva desde cero sea lo más simple
 * posible de armar y el foco quede 100% en el selector de talleres:
 * 1 taller REQUIRED con 2 horarios que se solapan a propósito (para ver el
 * aviso de conflicto) y 1 taller OPTIONAL sin solape.
 *
 * Corre de nuevo sin problema: crea un evento NUEVO cada vez (no busca uno
 * existente por nombre) — para "reiniciar" hay que borrar el evento
 * anterior a mano (cascada por FK) antes de volver a correr.
 *
 * php artisan db:seed --class=DemoTalleresRegistroSeeder
 */
class DemoTalleresRegistroSeeder extends Seeder
{
    public function run(): void
    {
        $pais = Pais::first() ?? Pais::factory()->create();
        $ciudad = Ciudad::where('pais_id', $pais->id)->first()
            ?? Ciudad::factory()->create(['pais_id' => $pais->id]);
        $organizador = Organizador::first() ?? Organizador::factory()->create();
        $tipoCongreso = TipoEvento::firstOrCreate(['nombre' => 'Congreso / No aplica']);
        $subtipo = SubtipoEvento::where('tipo_evento_id', $tipoCongreso->id)->first()
            ?? SubtipoEvento::factory()->create(['tipo_evento_id' => $tipoCongreso->id]);

        $evento = Evento::factory()->create([
            'organizador_id'    => $organizador->id,
            'tipo_evento_id'    => $tipoCongreso->id,
            'subtipo_evento_id' => $subtipo->id,
            'pais_id'           => $pais->id,
            'ciudad_id'         => $ciudad->id,
            'nombre'            => 'Congreso Demo Talleres (QA 18/08/2026)',
            'estado_evento_id'  => 'open',
            'publicado'         => '1',
            'fecha_apertura_inscrip' => now()->subDay(),
            'fecha_cierre_inscrip'   => now()->addDays(30),
            'fecha_inicio'           => now()->addDays(45),
            'fecha_fin'              => now()->addDays(45)->addHours(9),
            'hasDonation'            => true,
            'hasPromoCode'           => false,
            // Congresos con talleres (18/08/2026).
            'talleres_con_costo'     => true,
            // Inscripción en BOB y USD (18/08/2026) — apagado a propósito
            // en este demo para no mezclar 2 features nuevas en la misma
            // prueba; usar el evento de la feature BOB/USD para esa otra.
            'acepta_usd'             => false,
        ]);

        // Sin categorías: precio fijo en el form_type, participante no
        // elige categoría — mínimo de fricción para llegar rápido al paso
        // de talleres.
        $formType = FormType::factory()->create([
            'event_id'                  => $evento->id,
            'name'                      => 'Inscripción general',
            'tipo'                      => 'congreso',
            'precio_base'               => 100,
            'requiere_categoria'        => false,
            'requiere_talla'            => false,
            'requiere_distancia'        => false,
            'hasshirt'                  => false,
            'permite_inscripcion_grupal'=> false,
            'hasQuestion'               => false,
            'has_donation'              => true,
            'has_promo_code'            => false,
            'activo'                    => true,
            'cupo_total'                => 500,
        ]);

        // Taller REQUIRED — 2 horarios que se solapan a propósito (10:00-12:00
        // vs. 11:00-13:00) para poder ver el aviso de conflicto de horario en
        // el selector del frontend.
        $tallerObligatorio = Taller::factory()->create([
            'evento_id' => $evento->id,
            'nombre'    => 'Bioseguridad en eventos (obligatorio)',
            'modalidad' => 'REQUIRED',
            'precio'    => 30,
            'orden'     => 1,
        ]);

        SesionCongreso::factory()->create([
            'evento_id'   => $evento->id,
            'taller_id'   => $tallerObligatorio->id,
            'titulo'      => 'Bioseguridad — turno mañana',
            'ponente'     => 'Dra. Valeria Rojas',
            'sala'        => 'Sala A',
            'fecha'       => now()->addDays(45)->toDateString(),
            'hora_inicio' => '10:00:00',
            'hora_fin'    => '12:00:00',
            'cupo'        => 20,
        ]);

        SesionCongreso::factory()->create([
            'evento_id'   => $evento->id,
            'taller_id'   => $tallerObligatorio->id,
            // Override de precio por sesión (mayor al del taller) — para
            // ver que el precio efectivo puede variar por horario.
            'precio'      => 40,
            'titulo'      => 'Bioseguridad — turno mediodía',
            'ponente'     => 'Dr. Esteban Choque',
            'sala'        => 'Sala B',
            'fecha'       => now()->addDays(45)->toDateString(),
            'hora_inicio' => '11:00:00',
            'hora_fin'    => '13:00:00',
            'cupo'        => 20,
        ]);

        // Taller OPTIONAL — 1 sola sesión, sin solape con nada.
        $tallerOpcional = Taller::factory()->create([
            'evento_id' => $evento->id,
            'nombre'    => 'Networking e innovación (opcional)',
            'modalidad' => 'OPTIONAL',
            'precio'    => 20,
            'orden'     => 2,
        ]);

        SesionCongreso::factory()->create([
            'evento_id'   => $evento->id,
            'taller_id'   => $tallerOpcional->id,
            'titulo'      => 'Networking e innovación — bloque único',
            'ponente'     => 'María Fernanda Suárez',
            'sala'        => 'Terraza',
            'fecha'       => now()->addDays(45)->toDateString(),
            'hora_inicio' => '15:00:00',
            'hora_fin'    => '16:30:00',
            'cupo'        => 15,
        ]);

        // Ponencia suelta (sin taller) — se ve en agenda/check-in pero no
        // es seleccionable en el registro, mismo patrón que
        // DemoCongresoSeeder.
        SesionCongreso::factory()->create([
            'evento_id'   => $evento->id,
            'taller_id'   => null,
            'titulo'      => 'Keynote de apertura',
            'ponente'     => 'Jane Doe',
            'sala'        => 'Auditorio Principal',
            'fecha'       => now()->addDays(45)->toDateString(),
            'hora_inicio' => '09:00:00',
            'hora_fin'    => '09:45:00',
        ]);

        $this->command?->info(
            "Evento demo id={$evento->id} ('{$evento->nombre}') creado, ABIERTO y PUBLICADO — "
            . "form_type id={$formType->id}, taller obligatorio id={$tallerObligatorio->id} (2 sesiones solapadas), "
            . "taller opcional id={$tallerOpcional->id}."
        );
    }
}

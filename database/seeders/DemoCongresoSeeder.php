<?php

namespace Database\Seeders;

use App\Models\AsistenciaSesion;
use App\Models\Ciudad;
use App\Models\Evento;
use App\Models\FormType;
use App\Models\Organizador;
use App\Models\Pais;
use App\Models\Participante;
use App\Models\PresupuestoCategoria;
use App\Models\PresupuestoEvento;
use App\Models\Registration;
use App\Models\SesionCongreso;
use App\Models\SubtipoEvento;
use App\Models\TipoEvento;
use Illuminate\Database\Seeder;

/**
 * Seeder de demo para probar manualmente, en un solo evento congreso, las
 * 3 features de la sesión del 11/08/2026: Presupuesto de evento, Sesiones
 * de congreso + check-in, y Certificados automáticos. Pensado solo para
 * `event_testing` (o cualquier BD de prueba) — no correr contra una BD con
 * datos reales de organizadores/participantes.
 *
 * Corre de nuevo sin problema: crea un evento NUEVO cada vez (no busca uno
 * existente por nombre), así que si se quiere "limpiar y volver a armar"
 * hay que borrar el evento anterior a mano (cascada por FK) antes.
 *
 * php artisan db:seed --class=DemoCongresoSeeder
 */
class DemoCongresoSeeder extends Seeder
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
            'organizador_id' => $organizador->id,
            'tipo_evento_id' => $tipoCongreso->id,
            'subtipo_evento_id' => $subtipo->id,
            'pais_id' => $pais->id,
            'ciudad_id' => $ciudad->id,
            'estado_evento_id' => 'closed',
            'nombre' => 'Congreso Demo QA (seeder 11/08/2026)',
        ]);

        $formType = FormType::factory()->create(['event_id' => $evento->id]);

        $sesiones = [
            SesionCongreso::factory()->create([
                'evento_id' => $evento->id,
                'titulo' => 'Keynote de apertura',
                'ponente' => 'Jane Doe',
                'sala' => 'Auditorio Principal',
                'hora_inicio' => '09:00:00',
                'hora_fin' => '10:00:00',
            ]),
            SesionCongreso::factory()->create([
                'evento_id' => $evento->id,
                'titulo' => 'Taller de Laravel',
                'ponente' => 'John Smith',
                'sala' => 'Sala B',
                'hora_inicio' => '10:30:00',
                'hora_fin' => '12:00:00',
            ]),
            SesionCongreso::factory()->create([
                'evento_id' => $evento->id,
                'titulo' => 'Panel de cierre',
                'ponente' => 'María Pérez',
                'sala' => 'Auditorio Principal',
                'hora_inicio' => '15:00:00',
                'hora_fin' => '16:00:00',
            ]),
        ];

        // Participantes de prueba — uno sin correo a propósito, para
        // ejercitar el "se salta, no manda certificado" de
        // EnviarCertificadosCongresoAction.
        $participantesData = [
            ['nombre' => 'Ana', 'apellido' => 'Torres', 'correo' => 'ana.torres@example.com', 'sesiones' => [0, 1, 2]],
            ['nombre' => 'Luis', 'apellido' => 'Mamani', 'correo' => 'luis.mamani@example.com', 'sesiones' => [0, 1]],
            ['nombre' => 'Carla', 'apellido' => 'Vega', 'correo' => 'carla.vega@example.com', 'sesiones' => [2]],
            ['nombre' => 'Sin', 'apellido' => 'Correo', 'correo' => '', 'sesiones' => [0]],
        ];

        foreach ($participantesData as $i => $data) {
            $registration = Registration::factory()->create([
                'evento_id' => $evento->id,
                'form_types_id' => $formType->id,
                'referencia' => 'LA-DEMO-CONGRESO-' . ($i + 1),
                'fecha' => now(),
                'evento_nombre' => $evento->nombre,
                'tipo_pago' => 'pendiente',
                'pago_status' => 'paid',
            ]);

            $participante = Participante::create([
                'registration_id' => $registration->id,
                'nombre' => $data['nombre'],
                'apellido' => $data['apellido'],
                'genero' => 'Masculino',
                'tipo_documento' => 'DNI',
                'numero_documento' => (string) fake()->unique()->numerify('########'),
                'fecha_nacimiento' => '1990-01-01',
                'edad' => 35,
                'correo' => $data['correo'],
                'direccion' => 'Av. Demo 123',
                'ciudad' => $ciudad->nombre ?? 'La Paz',
                'telefono' => '70000000',
                'categoria' => '1',
                'subtotal' => 50,
            ]);

            foreach ($data['sesiones'] as $idx) {
                AsistenciaSesion::create([
                    'sesion_congreso_id' => $sesiones[$idx]->id,
                    'participante_id' => $participante->id,
                    'checkin_at' => now(),
                ]);
            }
        }

        // Presupuesto: ingresos y gastos manuales de ejemplo sobre el
        // mismo evento, usando el catálogo fijo sembrado por la migración
        // (Marketing/Logística/Premios/Patrocinio/Donación).
        $movimientos = [
            ['categoria' => 'Patrocinio', 'tipo' => 'ingreso', 'monto' => 5000],
            ['categoria' => 'Donación', 'tipo' => 'ingreso', 'monto' => 800],
            ['categoria' => 'Marketing', 'tipo' => 'gasto', 'monto' => 1200],
            ['categoria' => 'Logística', 'tipo' => 'gasto', 'monto' => 2500],
            ['categoria' => 'Premios', 'tipo' => 'gasto', 'monto' => 600],
        ];

        foreach ($movimientos as $m) {
            $categoria = PresupuestoCategoria::where('nombre', $m['categoria'])->first();
            if (! $categoria) {
                continue; // catálogo todavía no sembrado en esta BD
            }

            PresupuestoEvento::create([
                'evento_id' => $evento->id,
                'presupuesto_categoria_id' => $categoria->id,
                'tipo' => $m['tipo'],
                'monto' => $m['monto'],
                'moneda' => 'BOB',
                'fecha' => now()->toDateString(),
                'comprobante_url' => null,
                'admin_user_id' => null,
            ]);
        }

        $this->command?->info(
            "Evento demo id={$evento->id} ('{$evento->nombre}') — "
            . count($sesiones) . ' sesiones, '
            . count($participantesData) . ' participantes, '
            . count($movimientos) . ' movimientos de presupuesto.'
        );
    }
}

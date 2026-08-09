<?php

namespace Database\Seeders;

use App\Models\TipoEvento;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TipoEventoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tipos = [
            ['nombre' => 'Carrera de Ruta', 'icono' => '🏃'],
            ['nombre' => 'Trail Running', 'icono' => '⛰️'],
            ['nombre' => 'Ciclismo', 'icono' => '🚴'],
            ['nombre' => 'Caminata', 'icono' => '🚶'],
            ['nombre' => 'Triatlón', 'icono' => '🏆'],
            ['nombre' => 'Natación', 'icono' => '🏊'],
            // No es una disciplina deportiva — existe para que un evento no
            // deportivo (congreso, taller, etc.) tenga un valor válido en la
            // columna NOT NULL `eventos.tipo_evento_id`, y para que el
            // endpoint de consumo (GET /event/consumo) pueda excluirlos.
            // Ver database/migrations/2026_08_05_120000_add_congreso_tipo_evento.php.
            ['nombre' => 'Congreso / No aplica', 'icono' => '🏛️'],
        ];

        foreach ($tipos as $tipo) {
            TipoEvento::create([
                'nombre' => $tipo['nombre'],
                'icono' => $tipo['icono'],
                'activo' => true,
            ]);
        }
    }
}

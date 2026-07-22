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

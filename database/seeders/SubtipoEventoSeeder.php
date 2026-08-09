<?php

namespace Database\Seeders;

use App\Models\SubtipoEvento;
use App\Models\TipoEvento;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SubtipoEventoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $subtipos = [
            'Carrera de Ruta' => ['5K', '10K', '21K - Media Maratón', '42K - Maratón'],
            'Trail Running' => ['Trail Corto', 'Trail Largo', 'Ultra Trail'],
            'Ciclismo' => ['Ruta', 'Montaña (MTB)', 'Contrarreloj'],
            'Caminata' => ['Caminata Familiar', 'Caminata Solidaria'],
            'Triatlón' => ['Sprint', 'Olímpico', 'Half Ironman'],
            'Natación' => ['Aguas Abiertas', 'Piscina'],
            'Congreso / No aplica' => ['General'],
        ];

        foreach ($subtipos as $tipoNombre => $nombres) {
            $tipoEvento = TipoEvento::where('nombre', $tipoNombre)->first();

            if (! $tipoEvento) {
                continue;
            }

            foreach ($nombres as $nombre) {
                SubtipoEvento::create([
                    'tipo_evento_id' => $tipoEvento->id,
                    'nombre' => $nombre,
                    'activo' => true,
                ]);
            }
        }
    }
}

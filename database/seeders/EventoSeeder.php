<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Evento;
use App\Models\FormType;
class EventoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
            Evento::factory(2)
            ->has(
                // Por cada evento, crea 3 form_types relacionados automáticamente
                //FormType::factory()->count(3),'formTypes' // Nombre de la relación en tu modelo Evento
                 FormType::factory(3)->hasSouvenirs(3),'formTypes'
            )
            ->hasCoordinates(1)
            ->hasRoutes(5)
            ->hasCategories(3)
            ->hasPromoCodes(3)
            ->create();
    }
}

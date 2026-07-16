<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Evento;

class EventoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
            Evento::factory(50)->hasCoordinates(1)->hasRoutes(5)->hasCategories(3)->hasFormTypes(3)->hasSouvenirs(2)->hasPromoCodes(3)->create();
    }
}

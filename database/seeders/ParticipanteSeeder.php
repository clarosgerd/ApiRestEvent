<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Participante;
use App\Models\ContactoEmergencia;

class ParticipanteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
         Participante::factory()
            ->count(100)
            ->has(
                ContactoEmergencia::factory(),
                'contactoEmergencia'
            )
            ->create();
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Persona;
use App\Models\ContactoEmergencia;

class PersonaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
          Persona::factory()
            ->count(100)
            ->has(
                ContactoEmergencia::factory(),
                'contactoEmergencia'
            )
            ->create();
    }
}

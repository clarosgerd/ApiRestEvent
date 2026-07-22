<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();
        $this->call([
            PaisSeeder::class,
            CiudadSeeder::class,
            TipoEventoSeeder::class,
            SubtipoEventoSeeder::class,
            OrganizadorSeeder::class,
            FormasPagoSeeder::class,
            EventoSeeder::class,
          //  FormTypeSeeder::class,
          //  FormularioCamposSeeder::class,
            PersonaSeeder::class,

        ]);
    }
}

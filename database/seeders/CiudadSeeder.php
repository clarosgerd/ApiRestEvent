<?php

namespace Database\Seeders;

use App\Models\Ciudad;
use App\Models\Pais;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CiudadSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Pais::all()->each(function (Pais $pais) {
            Ciudad::factory(random_int(2, 5))->create([
                'pais_id' => $pais->id,
            ]);
        });
    }
}

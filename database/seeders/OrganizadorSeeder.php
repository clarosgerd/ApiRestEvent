<?php

namespace Database\Seeders;

use App\Models\Organizador;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class OrganizadorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Organizador::factory(8)->create();
    }
}

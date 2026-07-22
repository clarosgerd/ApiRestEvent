<?php

namespace Database\Factories;

use App\Models\Ciudad;
use App\Models\Pais;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ciudad>
 */
class CiudadFactory extends Factory
{
    protected $model = Ciudad::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pais_id' => Pais::inRandomOrder()->value('id') ?? Pais::factory(),
            'nombre' => fake()->city(),
            'activo' => true,
        ];
    }
}

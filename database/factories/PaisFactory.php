<?php

namespace Database\Factories;

use App\Models\Pais;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pais>
 */
class PaisFactory extends Factory
{
    protected $model = Pais::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->country(),
            'iso2' => fake()->unique()->countryCode(),
            'iso3' => fake()->countryISOAlpha3(),
            'prefijo_tel' => '+' . fake()->numberBetween(1, 999),
            'bandera_url' => fake()->imageUrl(64, 64, 'flags', true),
            'activo' => true,
        ];
    }
}

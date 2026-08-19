<?php

namespace Database\Factories;

use App\Models\FormasPago;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormasPago>
 */
class FormasPagoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => $this->faker->unique()->slug(2),
            'nombre' => $this->faker->words(2, true),
            'descripcion' => $this->faker->sentence(),
            'pasarela' => null,
            'tipo' => 'manual',
            'organizador_id' => null,
            'config' => null,
            'activo' => true,
        ];
    }
}

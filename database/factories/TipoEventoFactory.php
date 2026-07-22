<?php

namespace Database\Factories;

use App\Models\TipoEvento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TipoEvento>
 */
class TipoEventoFactory extends Factory
{
    protected $model = TipoEvento::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->words(2, true),
            'icono' => fake()->randomElement(['🏃', '🚴', '🏊', '🚶', '⛰️', '🏆']),
            'activo' => true,
        ];
    }
}

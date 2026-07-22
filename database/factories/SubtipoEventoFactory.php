<?php

namespace Database\Factories;

use App\Models\SubtipoEvento;
use App\Models\TipoEvento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubtipoEvento>
 */
class SubtipoEventoFactory extends Factory
{
    protected $model = SubtipoEvento::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tipo_evento_id' => TipoEvento::inRandomOrder()->value('id') ?? TipoEvento::factory(),
            'nombre' => fake()->unique()->words(2, true),
            'activo' => true,
        ];
    }
}

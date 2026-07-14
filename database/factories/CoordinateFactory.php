<?php

namespace Database\Factories;

use App\Models\Coordinate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Coordinate>
 */
class CoordinateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => \App\Models\Evento::factory(),
            'lat' => $this->faker->latitude(),  // Generates random float between -90 and 90
            'lng' => $this->faker->longitude(), // Generates random float between -180 and 180
        
        ];
    }
}

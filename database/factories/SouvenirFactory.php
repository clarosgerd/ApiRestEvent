<?php

namespace Database\Factories;

use App\Models\Souvenir;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Souvenir>
 */
class SouvenirFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
          $emoji = fake()->randomElement(['🧢','🍶','🎒','♿' ,'🏅','🎯']);
        return [
            //
            'event_id' => \App\Models\Evento::factory(),
            'name' => $this->faker->word(),
            'icon' => $emoji ,    
            'price' => $this->faker->randomFloat(2, 10, 100),
        ];
    }
}

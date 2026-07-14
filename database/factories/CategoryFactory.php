<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $emoji = fake()->randomElement(['🏃','🏢','✈️','♿' ]);
        return [
            //
            'event_id' => \App\Models\Evento::factory(),
            'name' => $this->faker->word(),
            'price' => $this->faker->randomFloat(2, 0, 1000),
            'description' => $this->faker->sentence(),
            'color' =>$this->faker->safeHexColor(),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\QuestionOptions;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuestionOptions>
 */
class QuestionOptionsFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'question_id'  => \App\Models\FormularioCampos::factory(),
            'option_text'  => fake()->word(),
            'order'        => fake()->numberBetween(1, 10),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\FormularioCampos;
use App\Models\QuestionOptions;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormularioCampos>
 */
class FormularioCamposFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
          $seccion = fake()->randomElement(['personal','kit','encuesta','legal','otro']);
          $tipo_input = fake()->randomElement(['text','email','tel','date','number','select','checkbox','radio','textarea','file']);
       
        return [
            //

           'form_types_id' => \App\Models\FormType::factory(),
            'seccion' =>  $seccion,
            'nombre_campo' => $this->faker->word(),
            'etiqueta' => $this->faker->sentence(),
            'tipo_input' =>  $tipo_input,
            'opciones' => json_encode([
                'key' => fake()->randomElement(['1', '2']),
                'value' => fake()->randomElement(['opcion A', 'opcion A', 'opcion A']),
            ]),
            'placeholder' => $this->faker->word(),
            'obligatorio' => $this->faker->boolean(), // 1: USD, 2: EUR
            'visible_en_reporte' => $this->faker->boolean(),
            'orden' => $this->faker->randomElement(['1', '2']),
           
        ];
    }

    public function hasQuestionOptions(int $count = 3): static
    {
        return $this->has(QuestionOptions::factory()->count($count), 'options');
    }
}
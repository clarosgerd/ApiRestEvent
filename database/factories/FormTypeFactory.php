<?php

namespace Database\Factories;

use App\Models\FormType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormType>
 */
class FormTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $emoji = fake()->randomElement(['🏃','🏢','✈️','♿' ]);
        $tipo = $this->faker->randomElement(['deportivo',
                'congreso',
                'taller',
                'corporativo',
                'cultural',
                'social',
                'educativo',
                'recreativo',
                'religioso',
                'gastronomico',
                'musical',
                'tecnologico',
                'artes',
                'literario',
                'ambiental',
                'salud',
                'moda',
                'teatro',
                'cine',
                'fotografia',
                'danza',
                'literatura']);
        return [
            //

            'event_id' => \App\Models\Evento::factory(),
            'name' => $this->faker->word(),
            'icon' => $emoji,    
            'description' => $this->faker->sentence(),
            'tipo' =>  $tipo,
            'cupo_total' => $this->faker->numberBetween(1, 100),
            'precio_base' => $this->faker->randomFloat(2, 0, 1000),
            'moneda' => $this->faker->randomElement([1, 2]), // 1: USD, 2: EUR
            'permite_lista_espera' => $this->faker->boolean(),
            'requiere_categoria' => $this->faker->boolean(),
            'requiere_talla' => $this->faker->boolean(),
            'requiere_distancia' => $this->faker->boolean(),
            'requiere_fecha_nacim' => $this->faker->boolean(),
            'permite_inscripcion_grupal' => $this->faker->boolean(),
            'max_integrantes_grupo' => $this->faker->numberBetween(1, 60),
            'permite_inscripcion_tercero' => $this->faker->boolean(),
            'costo_edicion' => $this->faker->randomFloat(2, 0, 100),
            'tiempo_expiracion_min' => $this->faker->numberBetween(1, 60),
            'texto_boton' => $this->faker->word(),
            'color' => $this->faker->hexColor(),
             'activo' => $this->faker->boolean(),
        ];
    }
}

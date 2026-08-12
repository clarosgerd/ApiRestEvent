<?php

namespace Database\Factories;

use App\Models\FormType;
use App\Models\FormularioCampos;
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
            'hasshirt' => $this->faker->boolean(),
            'permite_inscripcion_grupal' => $this->faker->boolean(),
            'max_integrantes_grupo' => $this->faker->numberBetween(5, 20),
            'descuento_registrante_pct' => 0.10,
            'hasQuestion' => $this->faker->boolean(),
            'costo_edicion' => $this->faker->randomFloat(2, 0, 100),
            'tiempo_expiracion_min' => $this->faker->numberBetween(1, 60),
            'texto_boton' => $this->faker->word(),
            'color' => $this->faker->hexColor(),
            // Kit/tallas/stock (11/08/2026) — antes `activo` no lo leía
            // nada al aceptar una inscripción nueva (ver "Hallazgo
            // adicional" en PRD-kit-tallas-stock-lista-espera.md), así
            // que un booleano al azar acá era inofensivo. Ahora
            // CrearInscripcionAction rechaza la inscripción si
            // `activo=false` — un form_type de fábrica "recién creado,
            // con cupo" debe arrancar activo por default; los tests que
            // quieran probar el caso "cupo lleno" lo desactivan
            // explícito con ->create(['activo' => false]).
             'activo' => true,
        ];
    }

    public function hasFormularioCampos(int $count = 3): static
    {
        return $this->has(
            FormularioCampos::factory()->hasQuestionOptions(3)->count($count),
            'formularioCampos'
        );
    }
}

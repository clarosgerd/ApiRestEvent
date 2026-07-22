<?php

namespace Database\Factories;

use App\Models\Ciudad;
use App\Models\Organizador;
use App\Models\Pais;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organizador>
 */
class OrganizadorFactory extends Factory
{
    protected $model = Organizador::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $paisId = Pais::inRandomOrder()->value('id');
        $ciudadId = $paisId
            ? Ciudad::where('pais_id', $paisId)->inRandomOrder()->value('id')
            : null;

        return [
            'razon_social' => fake()->company(),
            'nombre_comercial' => fake()->boolean(70) ? fake()->company() : null,
            'rut_nit' => fake()->unique()->numerify('##########'),
            'email' => fake()->unique()->companyEmail(),
            'telefono' => fake()->phoneNumber(),
            'pais_id' => $paisId,
            'ciudad_id' => $ciudadId,
            'direccion' => fake()->address(),
            'logo_url' => fake()->imageUrl(200, 200, 'business', true),
            'plan_id' => fake()->numberBetween(1, 3),
            'comision_especial' => fake()->optional(0.3)->randomFloat(2, 1, 15),
            'convenio_notas' => fake()->optional()->sentence(),
            'activo' => true,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Evento;
use App\Models\Taller;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Taller>
 */
class TallerFactory extends Factory
{
    protected $model = Taller::class;

    public function definition(): array
    {
        return [
            'evento_id'   => Evento::factory(),
            'nombre'      => $this->faker->sentence(3),
            'descripcion' => $this->faker->paragraph(),
            'modalidad'   => $this->faker->randomElement(['REQUIRED', 'OPTIONAL']),
            'precio'      => $this->faker->randomElement([null, 25, 50, 75]),
            'orden'       => 0,
            'activo'      => true,
        ];
    }
}
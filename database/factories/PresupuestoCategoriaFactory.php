<?php

namespace Database\Factories;

use App\Models\PresupuestoCategoria;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PresupuestoCategoria>
 */
class PresupuestoCategoriaFactory extends Factory
{
    protected $model = PresupuestoCategoria::class;

    public function definition(): array
    {
        return [
            'nombre' => $this->faker->word(),
            'tipo' => 'gasto',
            'activo' => true,
        ];
    }
}

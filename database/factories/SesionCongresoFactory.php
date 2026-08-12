<?php

namespace Database\Factories;

use App\Models\Evento;
use App\Models\SesionCongreso;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SesionCongreso>
 */
class SesionCongresoFactory extends Factory
{
    protected $model = SesionCongreso::class;

    public function definition(): array
    {
        return [
            'evento_id' => Evento::factory(),
            'titulo' => $this->faker->sentence(3),
            'ponente' => $this->faker->name(),
            'sala' => 'Sala A',
            'fecha' => now()->toDateString(),
            'hora_inicio' => '09:00:00',
            'hora_fin' => '10:00:00',
            'cupo' => null,
            'requiere_inscripcion' => false,
            'activa' => true,
        ];
    }
}

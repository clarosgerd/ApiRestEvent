<?php

namespace Database\Factories;

use App\Models\ContactoEmergencia;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactoEmergencia>
 */
class ContactoEmergenciaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
      return [

            'nombre' => fake()->name(),
         //   'celular' => fake()->phoneNumber(),
            'relacion' => fake()->randomElement([
                'FAT',
                'MOT',
                'BRO',
                'SIS',
                'WIF',
                'HUS',
                'SON',
                'DAU',
                'FRI'
            ]),
            'telefono' => fake()->phoneNumber(),
            'email' => fake()->safeEmail(),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Persona;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
/**
 * @extends Factory<Persona>
 */
class PersonaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sexo = fake()->randomElement([
            'Masculino',
            'Femenino'
        ]);
        $fakeCsrfToken = Str::random(40);
        return [
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('123456'),
            'nombre' => fake()->firstName(),
            'apellido' => fake()->lastName(),
            'alias' => fake()->userName(),
            'sexo' => $sexo,
            'tipo_documento' => fake()->randomElement([
                'DNI',
                'CI',
                'Pasaporte'
            ]),
            'numero_documento' => fake()->unique()->numerify('########'),
            'fecha_nacimiento' => fake()->dateTimeBetween('-60 years', '-18 years'),
            'correo' => fake()->safeEmail(),
            'direccion' => fake()->streetAddress(),
            'ciudad' => fake()->city(),
            'telefono' => fake()->phoneNumber(),
            'celular' => fake()->phoneNumber(),
            'token' => $fakeCsrfToken,
        ];
    }
}

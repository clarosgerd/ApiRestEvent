<?php

namespace Database\Factories;

use App\Models\PromoCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PromoCode>
 */
class PromoCodeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            //
            'event_id' => \App\Models\Evento::factory(),
           'promo_code' => strtoupper($this->faker->unique()->bothify('PROMO-#####')),
            'price' => $this->faker->randomFloat(2, 10, 100),
        ];
    }
}

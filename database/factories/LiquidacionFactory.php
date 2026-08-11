<?php

namespace Database\Factories;

use App\Models\Evento;
use App\Models\Liquidacion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Liquidacion>
 */
class LiquidacionFactory extends Factory
{
    protected $model = Liquidacion::class;

    public function definition(): array
    {
        return [
            'evento_id' => Evento::factory(),
            'monto_base' => 10.00,
            'cantidad_inscripciones' => 1,
            'liquidado_por_admin_user_id' => null,
            'liquidado_en' => now(),
        ];
    }
}

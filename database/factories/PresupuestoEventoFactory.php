<?php

namespace Database\Factories;

use App\Models\Evento;
use App\Models\PresupuestoCategoria;
use App\Models\PresupuestoEvento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PresupuestoEvento>
 */
class PresupuestoEventoFactory extends Factory
{
    protected $model = PresupuestoEvento::class;

    public function definition(): array
    {
        return [
            'evento_id' => Evento::factory(),
            'presupuesto_categoria_id' => PresupuestoCategoria::factory(),
            'tipo' => 'gasto',
            'monto' => 10.00,
            'moneda' => 'BOB',
            'fecha' => now()->toDateString(),
            'comprobante_url' => null,
            'admin_user_id' => null,
        ];
    }
}

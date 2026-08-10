<?php

namespace Database\Factories;

use App\Models\AdminUser;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<AdminUser>
 */
class AdminUserFactory extends Factory
{
    protected $model = AdminUser::class;

    public function definition(): array
    {
        return [
            'nombre'    => $this->faker->name(),
            'email'     => $this->faker->unique()->safeEmail(),
            'password'  => Hash::make('password'),
            'rol'       => 'super_admin',
            'evento_id' => null,
            'activo'    => true,
        ];
    }

    /**
     * Admin scoped a un único evento (rol 'admin', no 'super_admin') — ver
     * App\Http\Controllers\Concerns\AuthorizesEventoScope.
     */
    public function scopedTo(int $eventoId): static
    {
        return $this->state(fn () => [
            'rol'       => 'admin',
            'evento_id' => $eventoId,
        ]);
    }
}

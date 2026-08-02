<?php

namespace Database\Seeders;

use App\Models\AdminUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Siembra local de los 3 super_admin iniciales del panel de administración
 * de eventos (ver brain/PLAN-PANEL-ADMIN-EVENTOS-02082026.md §1.2). Correr
 * con: php artisan db:seed --class=AdminUserSeeder
 *
 * Credenciales placeholder, pensadas solo para desarrollo local — cambiar
 * antes de sembrar esto en cualquier ambiente real (ver nota sobre UAT sin
 * SSH/Terminal en el plan, §1.2, sobre cómo sembrar ahí en su momento).
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $superAdmins = [
            ['nombre' => 'Super Admin 1', 'email' => 'superadmin1@inscrito.test'],
            ['nombre' => 'Super Admin 2', 'email' => 'superadmin2@inscrito.test'],
            ['nombre' => 'Super Admin 3', 'email' => 'superadmin3@inscrito.test'],
        ];

        foreach ($superAdmins as $data) {
            AdminUser::updateOrCreate(
                ['email' => $data['email']],
                [
                    'nombre'   => $data['nombre'],
                    'password' => Hash::make('CambiarEsto123!'),
                    'rol'      => 'super_admin',
                    'evento_id' => null,
                    'activo'   => true,
                ]
            );
        }
    }
}

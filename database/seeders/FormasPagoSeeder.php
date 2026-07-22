<?php

namespace Database\Seeders;

use App\Models\FormasPago;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FormasPagoSeeder extends Seeder
{
    /**
     * Métodos de pago del sistema (organizador_id = NULL), disponibles como
     * base para cualquier organizador. Tigo queda fuera a propósito: es
     * simulado, sin convenio real.
     */
    public function run(): void
    {
        $metodos = [
            [
                'slug' => 'sip',
                'nombre' => 'QR (SIP)',
                'descripcion' => 'Pago con QR bancario vía SIP/MC4.',
                'pasarela' => 'sip',
                'tipo' => 'integrado',
                'organizador_id' => null,
                'activo' => true,
            ],
            [
                'slug' => 'multipago',
                'nombre' => 'Multipago',
                'descripcion' => 'Tigo Money, tarjeta, QR y pago en punto físico vía Multipago.',
                'pasarela' => 'multipago',
                'tipo' => 'integrado',
                'organizador_id' => null,
                'activo' => true,
            ],
            [
                'slug' => 'pendiente',
                'nombre' => 'Pago pendiente',
                'descripcion' => 'El participante deja el registro guardado y paga después.',
                'pasarela' => null,
                'tipo' => 'manual',
                'organizador_id' => null,
                'activo' => true,
            ],
        ];

        foreach ($metodos as $metodo) {
            FormasPago::updateOrCreate(['slug' => $metodo['slug']], $metodo);
        }
    }
}

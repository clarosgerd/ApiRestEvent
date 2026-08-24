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
            [
                // Pago pendiente USD (24/08/2026) — ver plan "Pago pendiente USD (link por
                // correo, expira 24h)". Solo se ofrece en eventos con usdPrecioFijo=true (ver
                // EventoResource::formasPago()) y solo si el organizador cargó un link de pago
                // en su pantalla "Formas de pago" (organizador_formas_pago.link_pago).
                'slug' => 'pendiente_usd',
                'nombre' => 'Pago pendiente (USD)',
                'descripcion' => 'Pago diferido para eventos con precio USD fijo — se envía un link de pago por correo, válido 24 horas.',
                'pasarela' => 'manual_usd',
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

<?php

namespace Tests\Feature;

use App\Models\LeadCapturado;
use App\Support\LeadsCapturadosData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ArmaExpositores;
use Tests\TestCase;

/**
 * SmartStand (25/09/2026) — estadísticas del dashboard de leads.
 */
class LeadsCapturadosDataTest extends TestCase
{
    use RefreshDatabase, ArmaExpositores;

    public function test_sin_leads_devuelve_ceros_y_no_rompe(): void
    {
        $cuenta = $this->crearCuenta($this->crearEvento());

        $datos = LeadsCapturadosData::paraEmpresa($cuenta);

        $this->assertSame(0, $datos['totalLeads']);
        $this->assertSame([], $datos['porDia']);
        $this->assertSame([], $datos['porCiudad']);
        $this->assertNull($datos['calificacionPromedio']);
    }

    public function test_calcula_total_por_dia_por_ciudad_y_promedio(): void
    {
        $evento = $this->crearEvento();
        $cuenta = $this->crearCuenta($evento);
        $otra = $this->crearCuenta($evento);
        $sucre1 = $this->crearAsistente($evento, ['ciudad' => 'Sucre']);
        $sucre2 = $this->crearAsistente($evento, ['ciudad' => ' Sucre ']);
        $lapaz = $this->crearAsistente($evento, ['ciudad' => 'La Paz']);
        $sinCiudad = $this->crearAsistente($evento, ['ciudad' => '']);

        $hoy = now()->startOfDay()->addHours(10);
        LeadCapturado::create(['empresa_expositora_id' => $cuenta->id, 'participante_id' => $sucre1->id, 'capturado_at' => $hoy, 'calificacion' => 5]);
        LeadCapturado::create(['empresa_expositora_id' => $cuenta->id, 'participante_id' => $sucre2->id, 'capturado_at' => $hoy->copy()->addHour(), 'calificacion' => 3]);
        LeadCapturado::create(['empresa_expositora_id' => $cuenta->id, 'participante_id' => $lapaz->id, 'capturado_at' => $hoy->copy()->subDay()]);
        LeadCapturado::create(['empresa_expositora_id' => $cuenta->id, 'participante_id' => $sinCiudad->id, 'capturado_at' => $hoy]);
        // Un lead de OTRA empresa no debe contarse.
        LeadCapturado::create(['empresa_expositora_id' => $otra->id, 'participante_id' => $lapaz->id, 'capturado_at' => $hoy]);

        $datos = LeadsCapturadosData::paraEmpresa($cuenta);

        $this->assertSame(4, $datos['totalLeads']);
        $this->assertSame(
            [
                ['fecha' => $hoy->copy()->subDay()->toDateString(), 'total' => 1],
                ['fecha' => $hoy->toDateString(), 'total' => 3],
            ],
            $datos['porDia']
        );
        $this->assertSame(2, collect($datos['porCiudad'])->firstWhere('ciudad', 'Sucre')['total']);
        $this->assertSame(1, collect($datos['porCiudad'])->firstWhere('ciudad', 'La Paz')['total']);
        $this->assertSame(1, collect($datos['porCiudad'])->firstWhere('ciudad', 'Sin dato')['total']);
        $this->assertSame(4.0, $datos['calificacionPromedio'], 'Promedio solo de los leads calificados (5 y 3).');
    }
}

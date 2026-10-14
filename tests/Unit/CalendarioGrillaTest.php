<?php

namespace Tests\Unit;

use App\Support\CalendarioGrilla;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * Grilla del calendario del evento (05/10/2026): mes, semana y día. Lógica pura,
 * sin Laravel ni HTTP.
 */
class CalendarioGrillaTest extends TestCase
{
    private function bloque(string $fecha, string $inicio, string $titulo): array
    {
        return ['tipo' => 'agenda', 'titulo' => $titulo, 'fecha' => $fecha, 'hora_inicio' => $inicio, 'hora_fin' => null, 'sala' => null, 'ponente' => null];
    }

    public function test_mes_de_noviembre_2026_tiene_6_semanas_que_empiezan_en_lunes(): void
    {
        $grilla = CalendarioGrilla::mes(Carbon::parse('2026-11-10'), [], null, null);

        $this->assertCount(6, $grilla['semanas']);
        $this->assertSame('2026-10-26', $grilla['semanas'][0][0]['fecha']);
        $this->assertSame('2026-12-06', $grilla['semanas'][5][6]['fecha']);
        foreach ($grilla['semanas'] as $semana) {
            $this->assertCount(7, $semana);
        }
    }

    public function test_mes_de_enero_2026_tiene_5_semanas(): void
    {
        $grilla = CalendarioGrilla::mes(Carbon::parse('2026-02-10'), [], null, null);

        $this->assertCount(5, $grilla['semanas']);
    }

    public function test_bloque_cae_en_su_dia_y_rango_del_evento_se_marca(): void
    {
        $bloques = [$this->bloque('2026-11-11', '09:00:00', 'Charla')];

        $grilla = CalendarioGrilla::mes(Carbon::parse('2026-11-10'), $bloques, '2026-11-10', '2026-11-12');

        $celdas = array_merge(...$grilla['semanas']);
        $porFecha = array_column($celdas, null, 'fecha');
        $this->assertSame(['Charla'], array_column($porFecha['2026-11-11']['bloques'], 'titulo'));
        $this->assertSame([], $porFecha['2026-11-10']['bloques']);
        $this->assertTrue($porFecha['2026-11-12']['enEvento']);
        $this->assertFalse($porFecha['2026-11-13']['enEvento']);
        $this->assertFalse($porFecha['2026-10-31']['enMes']);
    }

    public function test_semana_empieza_en_lunes_y_tiene_7_dias(): void
    {
        $grilla = CalendarioGrilla::semana(Carbon::parse('2026-11-10'), [], null, null);

        $this->assertCount(7, $grilla['dias']);
        $this->assertSame('2026-11-09', $grilla['dias'][0]['fecha']);
        $this->assertSame('2026-11-15', $grilla['dias'][6]['fecha']);
    }

    public function test_dia_ordena_los_bloques_por_hora(): void
    {
        $bloques = [
            $this->bloque('2026-11-10', '15:00:00', 'Tarde'),
            $this->bloque('2026-11-10', '08:30:00', 'Mañana'),
            $this->bloque('2026-11-11', '08:00:00', 'Otro día'),
        ];

        $dia = CalendarioGrilla::dia(Carbon::parse('2026-11-10'), $bloques, null, null);

        $this->assertSame(['Mañana', 'Tarde'], array_column($dia['bloques'], 'titulo'));
    }

    public function test_navegacion_de_mes_no_se_desborda(): void
    {
        $nav = CalendarioGrilla::navegacion('mes', Carbon::parse('2026-01-31'));

        $this->assertSame('2025-12-31', $nav['anterior']);
        $this->assertSame('2026-02-28', $nav['siguiente']);
    }
}

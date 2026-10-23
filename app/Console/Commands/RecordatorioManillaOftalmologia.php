<?php

namespace App\Console\Commands;

use App\Actions\EnviarRecordatorioManillaOftalmologiaAction;
use Illuminate\Console\Command;

/**
 * Envío puntual, único (22/10/2026, pedido real del usuario) — recordatorio
 * de recojo de manilla para el XXXI Congreso Boliviano de Oftalmología
 * (evento 90017). No es parte del flujo automático de notificaciones:
 * fecha/lugar/horario de entrega todavía no estaban confirmados al armar
 * esto, así que se reciben por parámetro en vez de leerse del evento. Pensado
 * para correr una sola vez con --dry-run primero y --send recién con el OK.
 */
class RecordatorioManillaOftalmologia extends Command
{
    protected $signature = 'notificaciones:recordatorio-manilla-oftalmologia
        {evento=90017 : ID del evento}
        {--fecha= : Fecha del recojo, ej. "24 de octubre"}
        {--lugar= : Lugar de entrega}
        {--hora-inicio= : Hora de inicio}
        {--hora-cierre= : Hora de cierre}
        {--excluir= : Correos a excluir, separados por coma}
        {--send : Sin esto, solo lista destinatarios sin enviar nada (dry-run)}';

    protected $description = 'Manda el recordatorio de recojo de manilla a los participantes pagados de un evento (envío puntual, no automático).';

    public function handle(EnviarRecordatorioManillaOftalmologiaAction $action): int
    {
        $eventoId   = (int) $this->argument('evento');
        $fecha      = $this->option('fecha');
        $lugar      = $this->option('lugar');
        $horaInicio = $this->option('hora-inicio');
        $horaCierre = $this->option('hora-cierre');

        if (!$fecha || !$lugar || !$horaInicio || !$horaCierre) {
            $this->error('Faltan datos: --fecha, --lugar, --hora-inicio y --hora-cierre son todos obligatorios.');

            return self::FAILURE;
        }

        $info = $action->destinatarios($eventoId);

        if ($info['inscripciones'] === 0) {
            $this->warn("No hay inscripciones pagadas para el evento {$eventoId}.");

            return self::SUCCESS;
        }

        $excluidos = collect(explode(',', (string) $this->option('excluir')))
            ->map(fn ($c) => strtolower(trim($c)))
            ->filter();
        $destinatarios = $info['destinatarios']->reject(
            fn ($correo) => $excluidos->contains(strtolower(trim($correo)))
        )->values();

        $this->info("Evento: {$info['evento_nombre']} (id {$eventoId})");
        $this->info("Inscripciones pagadas: {$info['inscripciones']} — destinatarios únicos: {$info['destinatarios']->count()} — excluidos: " . ($info['destinatarios']->count() - $destinatarios->count()));
        $this->info("Fecha: {$fecha} · Lugar: {$lugar} · Horario: {$horaInicio} a {$horaCierre}");

        if (!$this->option('send')) {
            $this->newLine();
            $this->warn('DRY-RUN — no se mandó nada. Revisá la lista y corré de nuevo con --send.');
            $this->table(['Correo'], $destinatarios->map(fn ($c) => [$c])->all());

            return self::SUCCESS;
        }

        $resultado = $action->enviar($info['evento_nombre'], $destinatarios, $fecha, $lugar, $horaInicio, $horaCierre);

        foreach ($resultado['fallidos'] as $f) {
            $this->error("Falló {$f}");
        }

        $this->newLine();
        $this->info("Enviados: {$resultado['enviados']} — Fallidos: " . count($resultado['fallidos']));

        return self::SUCCESS;
    }
}

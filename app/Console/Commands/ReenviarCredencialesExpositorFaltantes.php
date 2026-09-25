<?php

namespace App\Console\Commands;

use App\Actions\ProvisionarCuentaExpositorAction;
use App\Models\EmpresaExpositora;
use Illuminate\Console\Command;

/**
 * SmartStand (25/09/2026) — reconciliación de credenciales de expositor sin
 * enviar. El alta automática (ProvisionarCuentaExpositorAction) marca
 * `credenciales_enviadas_at` solo si el correo salió; si el SMTP falló, la
 * cuenta queda creada pero la empresa nunca recibió su acceso. Este comando
 * reintenta esos casos (regenerando la contraseña, porque la original solo
 * existe como hash).
 *
 * `--minutos` (default 15): no toca cuentas recién creadas, para no pisar un
 * primer envío que todavía está en curso dentro del callback de la pasarela.
 * Solo cuentas activas. `--dry-run` solo lista.
 */
class ReenviarCredencialesExpositorFaltantes extends Command
{
    protected $signature = 'expositores:reenviar-credenciales-faltantes
        {--dias=30 : Solo cuentas creadas en los últimos N días}
        {--minutos=15 : Ignora cuentas creadas hace menos de N minutos}
        {--dry-run : Solo lista, no envía nada}';

    protected $description = 'Reintenta el envío de credenciales a empresas expositoras cuyo correo de acceso nunca salió (SMTP caído durante el alta).';

    public function handle(ProvisionarCuentaExpositorAction $provisionar): int
    {
        $query = EmpresaExpositora::whereNull('credenciales_enviadas_at')
            ->where('activo', true)
            ->where('created_at', '>=', now()->subDays((int) $this->option('dias')))
            ->where('created_at', '<=', now()->subMinutes((int) $this->option('minutos')));

        if ($this->option('dry-run')) {
            $faltantes = $query->get(['id', 'nombre', 'email']);
            $this->info("[dry-run] {$faltantes->count()} cuenta(s) de expositor sin credenciales enviadas:");
            foreach ($faltantes as $cuenta) {
                $this->line(" - #{$cuenta->id} {$cuenta->nombre} <{$cuenta->email}>");
            }

            return self::SUCCESS;
        }

        $enviadas = 0;
        $fallidas = 0;
        $query->chunkById(50, function ($cuentas) use ($provisionar, &$enviadas, &$fallidas) {
            foreach ($cuentas as $cuenta) {
                $provisionar->reenviarCredenciales($cuenta) ? $enviadas++ : $fallidas++;
            }
        });

        $this->info("Credenciales de expositor reenviadas: {$enviadas}. Fallidas: {$fallidas}.");

        return self::SUCCESS;
    }
}

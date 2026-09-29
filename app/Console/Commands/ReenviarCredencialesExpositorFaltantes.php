<?php

namespace App\Console\Commands;

use App\Actions\ProvisionarCuentaExpositorAction;
use App\Models\EmpresaExpositora;
use App\Models\Registration;
use Illuminate\Console\Command;

/**
 * SmartStand (25/09/2026) — reconciliación de credenciales de expositor sin
 * enviar. El alta automática (ProvisionarCuentaExpositorAction) marca
 * `credenciales_enviadas_at` solo si el correo salió; si el SMTP falló, la
 * cuenta queda creada pero la empresa nunca recibió su acceso. Este comando
 * reintenta esos casos (regenerando la contraseña, porque la original solo
 * existe como hash).
 *
 * Paso 0 nuevo (29/09/2026) — bug real en UAT: el alta automática entera
 * puede fallar antes de crear la cuenta (`Log::error` en
 * `NotificacionService::provisionarCuentaExpositorSiCorresponde()`, ver el
 * try/catch que la aísla a propósito de romper el pago) — ej. un archivo
 * faltante en el deploy hizo que `ProvisionarCuentaExpositorAction` ni
 * resolviera como clase. Ese caso NO dejaba ninguna fila en
 * `empresas_expositoras` que el paso de "credenciales sin enviar" pudiera
 * ver — la inscripción quedaba pagada y sin cuenta, para siempre, sin
 * ningún reintento automático. Ahora este comando primero busca
 * inscripciones pagadas de expositor sin cuenta y llama a
 * `ProvisionarCuentaExpositorAction::handle()` (idempotente, seguro de
 * reintentar) antes de pasar al paso de reenvío de credenciales.
 *
 * `--minutos` (default 15): no toca casos recientes, para no pisar un
 * primer intento que todavía está en curso dentro del callback de la
 * pasarela. Solo cuentas activas. `--dry-run` solo lista, no hace nada.
 */
class ReenviarCredencialesExpositorFaltantes extends Command
{
    protected $signature = 'expositores:reenviar-credenciales-faltantes
        {--dias=30 : Solo casos de los últimos N días}
        {--minutos=15 : Ignora casos recientes (hace menos de N minutos)}
        {--dry-run : Solo lista, no hace nada}';

    protected $description = 'Reconcilia el alta de empresas expositoras: crea cuentas que nunca se llegaron a crear y reintenta el envío de credenciales que quedaron sin mandar.';

    public function handle(ProvisionarCuentaExpositorAction $provisionar): int
    {
        $sinCuenta = Registration::where('pago_status', 'paid')
            ->whereHas('formType', fn ($q) => $q->where('es_expositor', true))
            ->whereDoesntHave('empresaExpositora')
            ->where('created_at', '>=', now()->subDays((int) $this->option('dias')))
            ->where('created_at', '<=', now()->subMinutes((int) $this->option('minutos')));

        $sinCredenciales = EmpresaExpositora::whereNull('credenciales_enviadas_at')
            ->where('activo', true)
            ->where('created_at', '>=', now()->subDays((int) $this->option('dias')))
            ->where('created_at', '<=', now()->subMinutes((int) $this->option('minutos')));

        if ($this->option('dry-run')) {
            $registrationsFaltantes = $sinCuenta->get(['id', 'referencia']);
            $this->info("[dry-run] {$registrationsFaltantes->count()} inscripción(es) pagada(s) de expositor sin cuenta creada:");
            foreach ($registrationsFaltantes as $registration) {
                $this->line(" - Registration #{$registration->id} ({$registration->referencia})");
            }

            $faltantes = $sinCredenciales->get(['id', 'nombre', 'email']);
            $this->info("[dry-run] {$faltantes->count()} cuenta(s) de expositor sin credenciales enviadas:");
            foreach ($faltantes as $cuenta) {
                $this->line(" - #{$cuenta->id} {$cuenta->nombre} <{$cuenta->email}>");
            }

            return self::SUCCESS;
        }

        $creadas = 0;
        $sinCuenta->chunkById(50, function ($registrations) use ($provisionar, &$creadas) {
            foreach ($registrations as $registration) {
                if ($provisionar->handle($registration)) {
                    $creadas++;
                }
            }
        });

        $enviadas = 0;
        $fallidas = 0;
        $sinCredenciales->chunkById(50, function ($cuentas) use ($provisionar, &$enviadas, &$fallidas) {
            foreach ($cuentas as $cuenta) {
                $provisionar->reenviarCredenciales($cuenta) ? $enviadas++ : $fallidas++;
            }
        });

        $this->info("Cuentas de expositor creadas retroactivamente: {$creadas}. Credenciales reenviadas: {$enviadas}. Fallidas: {$fallidas}.");

        return self::SUCCESS;
    }
}

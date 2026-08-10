<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Todas las tareas programadas escriben su salida acá (append, no overwrite)
// — sin esto, un `$this->info(...)` de un comando corrido por cron no queda
// en ningún lado (no es lo mismo que storage/logs/laravel.log, que solo
// recibe excepciones no atrapadas vía el manejador de errores de Laravel).
// Sirve para diferenciar "corrió y no había nada que hacer" de "nunca corrió".
$schedulerLog = storage_path('logs/scheduler.log');

// ── Operaciones del sistema (ver brain/PLAN-NOTIFICACIONES.md §4) ───────
Schedule::command('eventos:cerrar-finalizados')->daily()->appendOutputTo($schedulerLog);
Schedule::command('form_types:desactivar-cupo-lleno')->daily()->appendOutputTo($schedulerLog);

// ── Recordatorios de pago pendiente + reversión de cupo (§4 fase 4) ─────
// Orden importa: primero se manda el aviso de 15 días (si corresponde), la
// reversión se basa en cuándo se mandó ese aviso, no en el mismo día.
Schedule::command('notificaciones:recordatorio-pendientes')->daily()->appendOutputTo($schedulerLog);
Schedule::command('notificaciones:revertir-cupo')->daily()->appendOutputTo($schedulerLog);

// Complementa al ciclo diario de arriba (que cuenta hacia atrás desde la
// fecha del evento) — este cubre el plazo corto de cada form_type
// (tiempo_expiracion_min, ej. 30 min para completar un pago QR), sin el
// cual un pending de un evento lejano podía "secuestrar" cupo por meses.
// Cada 5 min, no diario — el plazo típico se mide en minutos.
Schedule::command('notificaciones:expirar-pendientes')->everyFiveMinutes()->appendOutputTo($schedulerLog);

// ── Recordatorio de KIT para pagados (§4 fase 5) ────────────────────────
Schedule::command('notificaciones:recordatorio-kit')->daily()->appendOutputTo($schedulerLog);

// ── WhatsApp OpenWA (§2.5 fase 7) ────────────────────────────────────────
// SendWhatsappMessageJob es ShouldQueue (QUEUE_CONNECTION=database) — sin un
// worker corriendo, los jobs se acumulan en la tabla `jobs` sin procesarse.
// En vez de una segunda línea de cron en cPanel para `queue:work`, se cuelga
// del mismo schedule:run que ya corre cada minuto, cada 8 horas.
Schedule::command('queue:work --stop-when-empty')->cron('0 */8 * * *')->appendOutputTo($schedulerLog);

// ── Marketing por gustos (§2.6/§6 fase 8) ───────────────────────────────
// Corre diario; el comando filtra internamente por el día del mes que
// corresponda a cada persona (dia_envio_marketing del organizador).
Schedule::command('notificaciones:marketing-mensual')->daily()->appendOutputTo($schedulerLog);

// ── Aviso de dashboard al organizador, 15 días antes del evento ─────────
// Corre diario; el comando filtra internamente por eventos "open" cuya
// fecha_inicio caiga exactamente 15 días en el futuro (idempotente vía
// evento_notifications, igual patrón que registration_notifications).
Schedule::command('notificaciones:recordatorio-dashboard-organizador')->daily()->appendOutputTo($schedulerLog);

// ── Backup diario de la BD a Google Drive (panel /ops/backups) ──────────
// backup:clean corre después para no purgar el backup recién creado —
// su estrategia de retención (config/backup.php) nunca borra el más
// reciente de todos modos, pero mantiene el orden lógico.
Schedule::command('backup:run --only-db')->daily()->appendOutputTo($schedulerLog);
Schedule::command('backup:clean')->daily()->appendOutputTo($schedulerLog);

<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── Operaciones del sistema (ver brain/PLAN-NOTIFICACIONES.md §4) ───────
Schedule::command('eventos:cerrar-finalizados')->daily();
Schedule::command('form_types:desactivar-cupo-lleno')->daily();

// ── Recordatorios de pago pendiente + reversión de cupo (§4 fase 4) ─────
// Orden importa: primero se manda el aviso de 15 días (si corresponde), la
// reversión se basa en cuándo se mandó ese aviso, no en el mismo día.
Schedule::command('notificaciones:recordatorio-pendientes')->daily();
Schedule::command('notificaciones:revertir-cupo')->daily();

// ── Recordatorio de KIT para pagados (§4 fase 5) ────────────────────────
Schedule::command('notificaciones:recordatorio-kit')->daily();

// ── WhatsApp OpenWA (§2.5 fase 7) ────────────────────────────────────────
// SendWhatsappMessageJob es ShouldQueue (QUEUE_CONNECTION=database) — sin un
// worker corriendo, los jobs se acumulan en la tabla `jobs` sin procesarse.
// En vez de una segunda línea de cron en cPanel para `queue:work`, se cuelga
// del mismo schedule:run que ya corre cada minuto, cada 8 horas.
Schedule::command('queue:work --stop-when-empty')->cron('0 */8 * * *');

// ── Marketing por gustos (§2.6/§6 fase 8) ───────────────────────────────
// Corre diario; el comando filtra internamente por el día del mes que
// corresponda a cada persona (dia_envio_marketing del organizador).
Schedule::command('notificaciones:marketing-mensual')->daily();

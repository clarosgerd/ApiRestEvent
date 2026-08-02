<?php

namespace App\Listeners;

use App\Models\BackupRun;
use Illuminate\Support\Facades\Context;
use Spatie\Backup\Events\BackupWasSuccessful;

/**
 * Única fuente de verdad para `backup_runs` — corre tanto para el cron
 * diario (routes/console.php) como para el botón manual del panel
 * `/ops/backups` (OpsBackupController::run() deja el id del usuario en
 * Context antes de llamar Artisan::call('backup:run', ...)).
 */
class RecordSuccessfulBackup
{
    public function handle(BackupWasSuccessful $event): void
    {
        $backup = $event->backupDestination->newestBackup();

        BackupRun::create([
            'type' => 'backup',
            'status' => 'success',
            'disk' => $event->backupDestination->diskName(),
            'filename' => $backup?->path(),
            'size_bytes' => $backup ? (int) $backup->sizeInBytes() : null,
            'triggered_by_user_id' => Context::get('backup_triggered_by'),
        ]);
    }
}

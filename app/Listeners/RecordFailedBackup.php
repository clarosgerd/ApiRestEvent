<?php

namespace App\Listeners;

use App\Models\BackupRun;
use Illuminate\Support\Facades\Context;
use Spatie\Backup\Events\BackupHasFailed;

class RecordFailedBackup
{
    public function handle(BackupHasFailed $event): void
    {
        BackupRun::create([
            'type' => 'backup',
            'status' => 'failed',
            'disk' => $event->backupDestination?->diskName(),
            'error_message' => $event->exception->getMessage(),
            'triggered_by_user_id' => Context::get('backup_triggered_by'),
        ]);
    }
}

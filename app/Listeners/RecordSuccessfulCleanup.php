<?php

namespace App\Listeners;

use App\Models\BackupRun;
use Spatie\Backup\Events\CleanupWasSuccessful;

class RecordSuccessfulCleanup
{
    public function handle(CleanupWasSuccessful $event): void
    {
        BackupRun::create([
            'type' => 'cleanup',
            'status' => 'success',
            'disk' => $event->backupDestination->diskName(),
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\BackupRun;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OpsBackupController extends Controller
{
    public function index(): View
    {
        $runs = BackupRun::with('triggeredBy:id,name,email')->latest('id')->paginate(20);

        return view('ops.backups', ['runs' => $runs]);
    }

    /**
     * Síncrono a propósito (decisión confirmada con el usuario) — la BD
     * actual es chica, no vale la pena una cola nueva solo para esto.
     * El resultado real (éxito/fallo, tamaño, archivo) lo escribe
     * App\Listeners\RecordSuccessfulBackup / RecordFailedBackup al vuelo
     * vía los eventos de spatie/laravel-backup, no este método — acá solo
     * se dispara y se refleja el código de salida en el flash message.
     */
    public function run(): RedirectResponse
    {
        Context::add('backup_triggered_by', auth()->id());

        $exitCode = Artisan::call('backup:run', ['--only-db' => true]);

        Context::forget('backup_triggered_by');

        if ($exitCode !== 0) {
            return back()->withErrors(['general' => 'El backup falló — revisá el detalle en la lista de abajo.']);
        }

        return back()->with('status', 'Backup ejecutado correctamente.');
    }

    /**
     * response()->streamDownload() devuelve Symfony\...\StreamedResponse,
     * NO Illuminate\Http\Response (son clases hermanas, ninguna extiende
     * a la otra) — el type hint viejo tipaba mal el retorno. Nunca se
     * disparó antes porque nunca hubo un backup exitoso para descargar
     * (Google Drive jamás llegó a funcionar, ver
     * DEPLOY-CHECKLIST-BACKUP-LOCAL-22082026.md) — recién con el backup
     * local (22/08/2026) hubo algo real que descargar y salió a la luz.
     */
    public function download(BackupRun $backupRun): StreamedResponse
    {
        abort_if(!$backupRun->filename || !$backupRun->disk, 404);

        $disk = Storage::disk($backupRun->disk);

        abort_if(!$disk->exists($backupRun->filename), 404, 'El archivo ya no existe en el destino (¿se limpió por retención?).');

        return response()->streamDownload(
            function () use ($disk, $backupRun) {
                fpassthru($disk->readStream($backupRun->filename));
            },
            basename($backupRun->filename)
        );
    }
}

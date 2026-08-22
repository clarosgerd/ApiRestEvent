<?php

namespace Tests\Feature;

use App\Models\BackupRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Regresión (22/08/2026) — OpsBackupController::download() tipaba su
 * retorno como Illuminate\Http\Response, pero response()->streamDownload()
 * devuelve Symfony\Component\HttpFoundation\StreamedResponse (clases
 * hermanas, ninguna extiende a la otra) — PHP rechazaba el retorno en
 * runtime con un TypeError (500 real en producción). Nunca se disparó
 * antes porque nunca hubo un backup exitoso para descargar (Google Drive
 * nunca llegó a funcionar) — recién salió a la luz con el backup local
 * (ver DEPLOY-CHECKLIST-BACKUP-LOCAL-22082026.md).
 */
class OpsBackupDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_descarga_un_backup_del_disco_local_sin_typeerror(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('Laravel/backup-test.zip', 'contenido-de-prueba');

        $backupRun = BackupRun::create([
            'type' => 'backup',
            'status' => 'success',
            'disk' => 'local',
            'filename' => 'Laravel/backup-test.zip',
            'size_bytes' => 19,
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('ops.backups.download', $backupRun))
            ->assertOk()
            ->assertStreamedContent('contenido-de-prueba');
    }

    public function test_404_si_el_archivo_ya_no_existe_en_el_disco(): void
    {
        $backupRun = BackupRun::create([
            'type' => 'backup',
            'status' => 'success',
            'disk' => 'local',
            'filename' => 'Laravel/no-existe.zip',
            'size_bytes' => 0,
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('ops.backups.download', $backupRun))
            ->assertNotFound();
    }
}

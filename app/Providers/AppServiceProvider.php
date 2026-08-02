<?php

namespace App\Providers;

use App\Listeners\RecordFailedBackup;
use App\Listeners\RecordSuccessfulBackup;
use App\Listeners\RecordSuccessfulCleanup;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use OpenWA\Client;
use Spatie\Backup\Events\BackupHasFailed;
use Spatie\Backup\Events\BackupWasSuccessful;
use Spatie\Backup\Events\CleanupWasSuccessful;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
         $this->app->singleton(Client::class, function () {
        return new Client([
            'baseUrl' => config('services.openwa.base_url'),
            'apiKey' => config('services.openwa.api_key'),
          //  'httpClient' => $mockGuzzleClient, // MockHandler de Guzzle
        ]);
    });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Historial de backups (ver app/Listeners/, tabla backup_runs) — este
        // proyecto no tiene auto-discovery de eventos activado, se registran
        // a mano.
        Event::listen(BackupWasSuccessful::class, RecordSuccessfulBackup::class);
        Event::listen(BackupHasFailed::class, RecordFailedBackup::class);
        Event::listen(CleanupWasSuccessful::class, RecordSuccessfulCleanup::class);
    }
}

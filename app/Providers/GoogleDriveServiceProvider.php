<?php

namespace App\Providers;

use Google\Client;
use Google\Service\Drive;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;
use Masbug\Flysystem\GoogleDriveAdapter;

/**
 * Disco 'google' para spatie/laravel-backup (ver config/filesystems.php,
 * config/backup.php). Auth por cuenta de servicio (setAuthConfig con el
 * JSON descargado de Google Cloud), no OAuth con refresh token — evita
 * lidiar con expiración/renovación en un cron desatendido. La carpeta de
 * destino es una carpeta normal de Drive compartida con el email de la
 * cuenta de servicio como Editor (`sharedFolderId`), no una unidad
 * compartida de Workspace.
 */
class GoogleDriveServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Storage::extend('google', function ($app, array $config) {
            $client = new Client();
            $client->setApplicationName(config('app.name', 'Laravel'));
            $client->setAuthConfig($config['serviceAccountPath']);
            $client->addScope(Drive::DRIVE);

            $service = new Drive($client);

            $options = [];
            if (!empty($config['sharedFolderId'])) {
                $options['sharedFolderId'] = $config['sharedFolderId'];
            }

            $adapter = new GoogleDriveAdapter($service, '/', $options);
            $driver = new Filesystem($adapter);

            return new FilesystemAdapter($driver, $adapter, $config);
        });
    }
}

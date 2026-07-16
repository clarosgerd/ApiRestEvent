<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use OpenWA\Client;
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
        //
    }
}

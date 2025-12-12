<?php

namespace FunnyDev\IpFilter;

use Exception;
use Illuminate\Foundation\Console\VendorPublishCommand;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\ServiceProvider;

class IpFilterServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any package services.
     *
     * @return void
     */
    public function boot(Router $router): void
    {
        $this->publishes([
            __DIR__ . '/../config/ip-filter.php' => config_path('ip-filter.php'),
            __DIR__.'/../app/Http/Controllers/IpFilterController.php' => app_path('Http/Controllers/IpFilterController.php'),
        ], 'ip-filter');

        try {
            if (!file_exists(config_path('ip-filter.php'))) {
                $this->commands([
                    VendorPublishCommand::class,
                ]);

                Artisan::call('vendor:publish', ['--provider' => 'FunnyDev\\IpFilter\\IpFilterServiceProvider', '--tag' => ['ip-filter']]);
            }
        } catch (Exception $e) {}
    }

    /**
     * Register any package services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/ip-filter.php', 'ip-filter'
        );
        $this->app->singleton(IpFilterSdk::class, function ($app) {
            return new IpFilterSdk();
        });
    }
}

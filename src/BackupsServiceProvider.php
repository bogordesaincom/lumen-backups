<?php

namespace Bogordesain\Backups;

use Illuminate\Support\ServiceProvider;
use Bogordesain\Backups\Commands\CleanupCommand;
use Bogordesain\Backups\Commands\RunCommand;
use Bogordesain\Backups\Commands\SetupCommand;
use Laravel\Lumen\Application;

class BackupsServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app instanceof Application) {
            $this->app->configure('lumen-backups');
        } else {
            if ($this->app->runningInConsole()) {
                $this->publishes([
                    __DIR__ . '/../config/lumen-backups.php' => config('juice-backups.php'),
                ], 'config');
            }
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                CleanupCommand::class,
                RunCommand::class,
                SetupCommand::class,
            ]);
        }
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/lumen-backups.php', 'lumen-backups'
        );
    }
}

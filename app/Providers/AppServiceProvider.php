<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->extend(\MongoDB\Laravel\Bus\MongoBatchRepository::class, function ($repository, $app) {
            $connection = $app->make('db')->connection($app->config->get('queue.batching.database'));

            return new \App\Bus\CustomMongoBatchRepository(
                $app->make(\Illuminate\Bus\BatchFactory::class),
                $connection,
                $app->config->get('queue.batching.collection', 'job_batches'),
            );
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

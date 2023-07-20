<?php

namespace App\Providers;

use App\Repositories\FixedAssetRepository;
use App\Repositories\VladimirTagGeneratorRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $repositories = [
            FixedAssetRepository::class,
            VladimirTagGeneratorRepository::class,
        ];

        foreach ($repositories as $repository) {
            $this->app->bind($repository, function () use ($repository) {
                return new $repository();
            });
        }
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}

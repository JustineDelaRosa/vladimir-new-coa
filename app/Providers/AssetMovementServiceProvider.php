<?php

namespace App\Providers;

use App\Services\AssetTransferServices;
use Illuminate\Support\ServiceProvider;

class AssetMovementServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $movements = [
            AssetTransferServices::class
        ];

        foreach ($movements as $movement) {
            $this->app->bind($movement, function () use ($movement) {
                return new $movement();
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

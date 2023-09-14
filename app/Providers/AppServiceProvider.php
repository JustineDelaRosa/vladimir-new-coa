<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Validator::extend('unique_in_array', function ($attribute, $value, $parameters, $validator) {
            return count($value) === count(array_unique($value));
        });
        Sanctum::$accessTokenAuthenticationCallback = function ($accessToken, $isValid){
            return !$accessToken->last_used_at || $accessToken->last_used_at->gte(now()->subMinutes(60));
        };
    }
}

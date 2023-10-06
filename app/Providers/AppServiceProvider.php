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
        Validator::extend('one_array_present', function ($attribute, $value, $parameters, $validator) {
            $otherArrayData = $validator->getData()[$parameters[0]] ?? [];
            return (empty($value) && !empty($otherArrayData)) || (!empty($value) && empty($otherArrayData));
        });
        Sanctum::$accessTokenAuthenticationCallback = function ($accessToken, $isValid){
            return !$accessToken->last_used_at || $accessToken->last_used_at->gte(now()->subMinutes(60));
        };
//        Validator::extend('validateAccountable', function ($attribute, $value, $parameters, $validator) {
//            $accountable = request()->input('accountable');
//
//            if (request()->accountability != 'Personal Issued') {
//                request()->merge(['accountable' => null]);
//                return true;
//            }
//
//            if (!empty($accountable['general_info']['full_id_number'])) {
//                $full_id_number = $accountable['general_info']['full_id_number'];
//                request()->merge(['accountable' => $full_id_number]);
//
//                return $full_id_number !== '';
//            }
//
//            return false;
//        });
    }
}

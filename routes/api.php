<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Setup\SetupController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();

Route::post('setup/department', [SetupController::class, 'createDepartment']);
Route::post('setup/role', [SetupController::class, 'createRole']);


Route::resource('user', UserController::class);

Route::post('/auth/login', [AuthController::class, 'Login']);

Route::group(['middleware' => ['auth:sanctum']], function() {

    //SETUP//
    Route::post('setup/module', [SetupController::class, 'createModule']);
    Route::get('setup/get-modules', [SetupController::class, 'getModule']);
    Route::put('setup/get-modules/archived-modules/{id}', [SetupController::class, 'archived']);

    ///AUTH//
    Route::put('auth/reset/{id}', [AuthController::class, 'resetPassword']);
    Route::get('auth/change_password', [AuthController::class, 'changedPassword']);
    Route::post('auth/logout', [AuthController::class, 'Logout']);

    //USER//
    Route::resource('user', UserController::class);
    Route::get('users/search', [UserController::class, 'search']);
    Route::put('user/archived-user/{id}', [UserController::class, 'archived']);
    
    
});




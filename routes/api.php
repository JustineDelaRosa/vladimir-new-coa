<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Setup\SetupController;
use App\Http\Controllers\CategoryListController;
use App\Http\Controllers\MajorCategoryController;
use App\Http\Controllers\MinorCategoryController;
use App\Http\Controllers\ServiceProviderController;


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

// Route::post('setup/department', [SetupController::class, 'createDepartment']);
Route::post('setup/role', [SetupController::class, 'createRole']);


Route::resource('user', UserController::class);

Route::post('/auth/login', [AuthController::class, 'Login']);
// Route::resource('user', UserController::class);

Route::group(['middleware' => ['auth:sanctum']], function() {

   

    //SETUP//
    Route::post('setup/module', [SetupController::class, 'createModule']);
    Route::get('setup/get-modules', [SetupController::class, 'getModule']);
    Route::put('setup/get-modules/archived-modules/{id}', [SetupController::class, 'archived']);
    Route::get('setup/getById/{id}', [SetupController::class, 'getModuleId']);
    Route::put('setup/update-modules/{id}',  [SetupController::class, 'updateModule']);

    ///AUTH//
    Route::put('auth/reset/{id}', [AuthController::class, 'resetPassword']);
    Route::get('auth/change_password', [AuthController::class, 'changedPassword']);
    Route::post('auth/logout', [AuthController::class, 'Logout']);

    //USER//
    Route::resource('user', UserController::class);
    Route::get('users/search', [UserController::class, 'search']);
    Route::put('user/archived-user/{id}', [UserController::class, 'archived']);
    Route::get('test', [UserController::class, 'test']);
    

    //ServiceProvider

    Route::resource('service-provider', ServiceProviderController::class);
    Route::put('service-provider/archived-service-provider/{id}', [ServiceProviderController::class, 'archived']);
    Route::get('service-providers/search', [ServiceProviderController::class, 'search']);

    
    //major category
    Route::resource('major-category', MajorCategoryController::class);
    Route::put('major-category/archived-major-category/{id}', [MajorCategoryController::class, 'archived']);
    Route::get('major-categories/search', [MajorCategoryController::class, 'search']);


    //minor category
    Route::resource('minor-category', MinorCategoryController::class);
    Route::put('minor-category/archived-minor-category/{id}', [MinorCategoryController::class, 'archived']);
    Route::get('minor-categories/search', [MinorCategoryController::class, 'search']);

    Route::resource('category-list', CategoryListController::class);
    Route::put('category-list/archived-category-list/{id}', [CategoryListController::class, 'archived']);
    Route::get('category-lists/search', [CategoryListController::class, 'search']);

    Route::resource('supplier', SupplierController::class);
    Route::put('supplier/archived-supplier/{id}', [SupplierController::class, 'archived']);
    Route::get('suppliers/search', [SupplierController::class, 'search']);
  
});




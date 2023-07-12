<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CategoryListController;
use App\Http\Controllers\Masterlist\CapexController;
use App\Http\Controllers\Masterlist\COA\AccountTitleController;
use App\Http\Controllers\Masterlist\COA\CompanyController;
use App\Http\Controllers\Masterlist\COA\DepartmentController;
use App\Http\Controllers\Masterlist\COA\LocationController;
use App\Http\Controllers\Masterlist\DivisionController;
use App\Http\Controllers\Masterlist\FixedAssetController;
use App\Http\Controllers\Masterlist\MajorCategoryController;
use App\Http\Controllers\Masterlist\MasterlistExportController;
use App\Http\Controllers\Masterlist\MasterlistImportController;
use App\Http\Controllers\Masterlist\MinorCategoryController;
use App\Http\Controllers\Masterlist\PrintBarcode\PrintBarCodeController;
use App\Http\Controllers\Masterlist\Status\AssetStatusController;
use App\Http\Controllers\Masterlist\Status\CycleCountStatusController;
use App\Http\Controllers\Masterlist\Status\DepreciationStatusController;
use App\Http\Controllers\Masterlist\Status\MovementStatusController;
use App\Http\Controllers\Masterlist\SubCapexController;
use App\Http\Controllers\Masterlist\TypeOfRequestController;
use App\Http\Controllers\ServiceProviderController;
use App\Http\Controllers\Setup\PrinterIPController;
use App\Http\Controllers\Setup\RoleManagementController;
use App\Http\Controllers\Setup\SetupController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;


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


// Route::resource('user', UserController::class);
Route::resource('role-management', RoleManagementController::class);
Route::put('role-management/archived-role-management/{id}', [RoleManagementController::class, 'archived']);
Route::get('search/role-management', [RoleManagementController::class, 'search']);

Route::post('/auth/login', [AuthController::class, 'Login']);
// Route::resource('user', UserController::class);





Route::group(['middleware' => ['auth:sanctum']], function () {

    //SETUP//
    Route::post('setup/module', [SetupController::class, 'createModule']);
    Route::get('setup/get-modules', [SetupController::class, 'getModule']);
    Route::put('setup/get-modules/archived-modules/{id}', [SetupController::class, 'archived']);
    Route::get('setup/getById/{id}', [SetupController::class, 'getModuleId']);
    Route::put('setup/update-modules/{id}',  [SetupController::class, 'updateModule']);

    //COMPANY//
    Route::resource('company', CompanyController::class);
    Route::get('companies/search', [CompanyController::class, 'search']);

    //DEPARTMENT//
    Route::resource('department', DepartmentController::class);
    Route::get('departments/search', [DepartmentController::class, 'search']);

    //LOCATION//
    Route::resource('location', LocationController::class);
    Route::get('locations/search', [LocationController::class, 'search']);

    //LOCATION//
    Route::resource('account-title', AccountTitleController::class);
    Route::get('account-titles/search', [AccountTitleController::class, 'search']);




    ///AUTH//
    Route::put('auth/reset/{id}', [AuthController::class, 'resetPassword']);
    Route::post('auth/change_password', [AuthController::class, 'changedPassword']);
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
    Route::put('category-list/add-update-minorcategory/{id}', [CategoryListController::class, 'UpdateMinorCategory']);

    Route::resource('supplier', SupplierController::class);
    Route::put('supplier/archived-supplier/{id}', [SupplierController::class, 'archived']);
    Route::get('suppliers/search', [SupplierController::class, 'search']);

    //division
    Route::resource('division', DivisionController::class);
    Route::put('division/archived-division/{id}', [DivisionController::class, 'archived']);
    Route::get('divisions/search', [DivisionController::class, 'search']);

    //Capex
    Route::resource('capex', CapexController::class);
    Route::patch('capex/archived-capex/{id}', [CapexController::class, 'archived']);
    Route::post('sub_capex/{id}', [CapexController::class, 'storeSubCapex']);
    //sub capex
    Route::resource('sub-capex', SubCapexController::class);
    Route::patch('sub-capex/archived-sub-capex/{id}', [SubCapexController::class, 'archived']);

    //materlist import
    Route::post('import-masterlist', [MasterlistImportController::class, 'masterlistImport']);
    //capex import
    Route::post('import-capex', [CapexController::class, 'capexImport']);
    //materlist export
    Route::get('export-masterlist', [MasterlistExportController::class, 'export']);

    //fixed asset
    Route::resource('fixed-asset', FixedAssetController::class);
    Route::patch('fixed-asset/archived-fixed-asset/{id}', [FixedAssetController::class, 'archived']);
    Route::get('fixed-assets/search', [FixedAssetController::class, 'search']);
    Route::get('fixed-assets/search-asset-tag', [FixedAssetController::class, 'searchAssetTag']);
    //Custom asset depreciation calculation
    Route::post('asset-depreciation/{id}', [FixedAssetController::class, 'assetDepreciation']);
    Route::get('show-fixed-asset/{tagNumber}', [FixedAssetController::class, 'showTagNumber']);

    //barcode
    Route::post('fixed-asset/barcode', [PrintBarCodeController::class, 'printBarcode']);

    //type of request
    Route::resource('type-of-request', TypeOfRequestController::class);
    Route::patch('type-of-request/archived-tor/{id}', [TypeOfRequestController::class, 'archived']);

    //PrinterIp
    Route::resource('printer-ip', PrinterIpController::class);
    Route::patch('activateIp/{id}', [PrinterIpController::class, 'activateIP']);
    Route::get('getIP', [PrinterIpController::class, 'getClientIP']);

    //COA Archive
    Route::patch('account-title/archived-account-title/{id}', [AccountTitleController::class, 'archived']);

    //STATUS
    //Asset Status
    Route::resource('asset-status', AssetStatusController::class);
    Route::patch('asset-status/archived-asset-status/{id}', [AssetStatusController::class, 'archived']);
    //Cycle Count Status
    Route::resource('cycle-count-status', CycleCountStatusController::class);
    Route::patch('cycle-count-status/archived-cycle-count-status/{id}', [CycleCountStatusController::class, 'archived']);
    //Depreciation Status
    Route::resource('depreciation-status', DepreciationStatusController::class);
    Route::patch('depreciation-status/archived-depreciation-status/{id}', [DepreciationStatusController::class, 'archived']);
    //Movement Status
    Route::resource('movement-status', MovementStatusController::class);
    Route::patch('movement-status/archived-movement-status/{id}', [MovementStatusController::class, 'archived']);
});

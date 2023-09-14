<?php

use App\Http\Controllers\ApproverSettingController;
use App\Http\Controllers\AssignApproverController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CategoryListController;
use App\Http\Controllers\Masterlist\AdditionalCostController;
use App\Http\Controllers\Masterlist\CapexController;
use App\Http\Controllers\Masterlist\COA\AccountTitleController;
use App\Http\Controllers\Masterlist\COA\CompanyController;
use App\Http\Controllers\Masterlist\COA\DepartmentController;
use App\Http\Controllers\Masterlist\COA\LocationController;
use App\Http\Controllers\Masterlist\DivisionController;
use App\Http\Controllers\Masterlist\FixedAssetController;
use App\Http\Controllers\Masterlist\MajorCategoryController;
use App\Http\Controllers\Masterlist\FixedAssetExportController;
use App\Http\Controllers\Masterlist\FixedAssetImportController;
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


// Route::resource('user', UserController::class);


Route::post('/auth/login', [AuthController::class, 'Login']);
// Route::resource('user', UserController::class);

//DOWNLOAD SAMPLE FILE//
Route::get('capex-sample-file', [CapexController::class, 'sampleCapexDownload']);
Route::get('fixed-asset-sample-file', [FixedAssetController::class, 'sampleFixedAssetDownload']);
Route::get('additional-cost-sample-file', [AdditionalCostController::class, 'sampleAdditionalCostDownload']);

Route::get('getIP', [PrinterIpController::class, 'getClientIP']);

Route::group(['middleware' => ['auth:sanctum']], function () {

    //ROLE MANAGEMENT
    Route::post('setup/role', [SetupController::class, 'createRole']);
    Route::resource('role-management', RoleManagementController::class);
    Route::put('role-management/archived-role-management/{id}', [RoleManagementController::class, 'archived']);
    Route::get('search/role-management', [RoleManagementController::class, 'search']);

    //SETUP//
    Route::post('setup/module', [SetupController::class, 'createModule']);
    Route::get('setup/get-modules', [SetupController::class, 'getModule']);
    Route::put('setup/get-modules/archived-modules/{id}', [SetupController::class, 'archived']);
    Route::get('setup/getById/{id}', [SetupController::class, 'getModuleId']);
    Route::put('setup/update-modules/{id}', [SetupController::class, 'updateModule']);

    //COMPANY//
    Route::resource('company', CompanyController::class);
    Route::get('companies/search', [CompanyController::class, 'search']);

    //DEPARTMENT//
    Route::resource('department', DepartmentController::class);
    Route::get('departments/search', [DepartmentController::class, 'search']);

    //LOCATION//
    Route::resource('location', LocationController::class);
    Route::get('locations/search', [LocationController::class, 'search']);

    //ACCOUNT TITLE//
    Route::resource('account-title', AccountTitleController::class);
    Route::get('account-titles/search', [AccountTitleController::class, 'search']);
    Route::patch('account-title/archived-account-title/{id}', [AccountTitleController::class, 'archived']);


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


    //MAJOR CATEGORY//
    Route::resource('major-category', MajorCategoryController::class);
    Route::put('major-category/archived-major-category/{id}', [MajorCategoryController::class, 'archived']);
    Route::get('major-categories/search', [MajorCategoryController::class, 'search']);


    //MINOR CATEGORY//
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

    //DIVISION//
    Route::resource('division', DivisionController::class);
    Route::put('division/archived-division/{id}', [DivisionController::class, 'archived']);
    Route::get('divisions/search', [DivisionController::class, 'search']);

    //CAPEX//
    Route::resource('capex', CapexController::class);
    Route::patch('capex/archived-capex/{id}', [CapexController::class, 'archived']);
    Route::post('sub_capex/{id}', [CapexController::class, 'storeSubCapex']);
    Route::get('capex-export', [CapexController::class, 'capexExport']);
    //SUB CAPEX//
    Route::resource('sub-capex', SubCapexController::class);
    Route::patch('sub-capex/archived-sub-capex/{id}', [SubCapexController::class, 'archived']);

    //MASTERLIST IMPORT//
    Route::post('import-masterlist', [FixedAssetImportController::class, 'masterlistImport']);
    //MASTERLIST EXPORT//
    Route::get('export-masterlist', [FixedAssetExportController::class, 'export']);
    //CAPEX IMPORT//
    Route::post('import-capex', [CapexController::class, 'capexImport']);


    //FIXED ASSET//
    Route::resource('fixed-asset', FixedAssetController::class);
    Route::patch('fixed-asset/archived-fixed-asset/{id}', [FixedAssetController::class, 'archived']);
    Route::get('fixed-asset-search', [FixedAssetController::class, 'search']);
    Route::get('fixed-assets/search-asset-tag', [FixedAssetController::class, 'searchAssetTag']);
    //ADDITIONAL COST//
    Route::resource('additional-cost', AdditionalCostController::class);
    Route::post('add-cost-depreciation/{id}', [AdditionalCostController::class, 'assetDepreciation']);
    Route::patch('add-cost/archived-add-cost/{id}', [AdditionalCostController::class, 'archived']);
    Route::post('import-add-cost', [AdditionalCostController::class, 'additionalCostImport']);

    //CUSTOM ASSET DEPRECIATION CALCULATION//
    Route::post('asset-depreciation/{id}', [FixedAssetController::class, 'assetDepreciation']);
    Route::get('show-fixed-asset/{tagNumber}', [FixedAssetController::class, 'showTagNumber']);

    //BARCODE//
    Route::post('fixed-asset/barcode', [PrintBarCodeController::class, 'printBarcode']);
    Route::get('print-barcode-show', [PrintBarCodeController::class, 'viewSearchPrint']);

    //TYPE OF REQUEST//
    Route::resource('type-of-request', TypeOfRequestController::class);
    Route::patch('type-of-request/archived-tor/{id}', [TypeOfRequestController::class, 'archived']);

    //PRINT IP//
    Route::resource('printer-ip', PrinterIpController::class);
    Route::patch('activateIp/{id}', [PrinterIpController::class, 'activateIP']);
//    Route::get('getIP', [PrinterIpController::class, 'getClientIP']);


    //STATUS//
    //ASSET STATUS
    Route::resource('asset-status', AssetStatusController::class);
    Route::patch('asset-status/archived-asset-status/{id}', [AssetStatusController::class, 'archived']);
    //CYCLE COUNT STATUS
    Route::resource('cycle-count-status', CycleCountStatusController::class);
    Route::patch('cycle-count-status/archived-cycle-count-status/{id}', [CycleCountStatusController::class, 'archived']);
    //DEPRECIATION STATUS
    Route::resource('depreciation-status', DepreciationStatusController::class);
    Route::patch('depreciation-status/archived-depreciation-status/{id}', [DepreciationStatusController::class, 'archived']);
    //MOVEMENT STATUS
    Route::resource('movement-status', MovementStatusController::class);
    Route::patch('movement-status/archived-movement-status/{id}', [MovementStatusController::class, 'archived']);

    //APPROVER SETTING//
    Route::resource('approver-setting', ApproverSettingController::class);
    Route::get('setup-approver', [ApproverSettingController::class, 'approverSetting']);
    Route::patch('approver-setting/archived-approver-setting/{id}', [ApproverSettingController::class, 'archived']);

    //ASSIGNING APPROVER//
    Route::resource('assign-approver', AssignApproverController::class);
    Route::get('requester-view', [AssignApproverController::class, 'requesterView']);
    Route::put('arrange-layer/{id}',[AssignApproverController::class, 'arrangeLayer']);
});



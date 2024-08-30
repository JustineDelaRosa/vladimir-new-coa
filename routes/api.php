<?php

use App\Http\Controllers\API\AddingPoController;
use App\Http\Controllers\API\AddingPrController;
use App\Http\Controllers\API\ApproverSettingController;
use App\Http\Controllers\API\Approvers\AssetDisposalApproverController;
use App\Http\Controllers\API\Approvers\AssetPullOutApproverController;
use App\Http\Controllers\API\Approvers\AssetTransferApproverController;
use App\Http\Controllers\API\AssetApprovalController;
use App\Http\Controllers\API\AssetApprovalLogger\AssetApprovalLoggerController;
use App\Http\Controllers\API\AssetMovement\Transfer\AssetTransferApprovalController;
use App\Http\Controllers\API\AssetMovement\Transfer\AssetTransferContainerController;
use App\Http\Controllers\API\AssetMovement\Transfer\AssetTransferRequestController;
use App\Http\Controllers\API\AssetMovement\TransferController;
use App\Http\Controllers\API\AssetReleaseController;
use App\Http\Controllers\API\AssetRequestController;
use App\Http\Controllers\API\ReceiveReceiptSummaryController;
use App\Http\Controllers\API\DepartmentUnitApproversController;
use App\Http\Controllers\API\RequestContainerController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Masterlist\AdditionalCostController;
use App\Http\Controllers\Masterlist\CapexController;
use App\Http\Controllers\Masterlist\COA\AccountTitleController;
use App\Http\Controllers\Masterlist\COA\BusinessUnitController;
use App\Http\Controllers\Masterlist\COA\CompanyController;
use App\Http\Controllers\Masterlist\COA\DepartmentController;
use App\Http\Controllers\Masterlist\COA\LocationController;
use App\Http\Controllers\Masterlist\COA\SubUnitController;
use App\Http\Controllers\Masterlist\COA\UnitController;
use App\Http\Controllers\Masterlist\DivisionController;
use App\Http\Controllers\Masterlist\FixedAssetController;
use App\Http\Controllers\Masterlist\FixedAssetExportController;
use App\Http\Controllers\Masterlist\FixedAssetImportController;
use App\Http\Controllers\Masterlist\MajorCategoryController;
use App\Http\Controllers\Masterlist\MinorCategoryController;
use App\Http\Controllers\Masterlist\PrintBarcode\MemoSeriesController;
use App\Http\Controllers\Masterlist\PrintBarcode\PrintBarCodeController;
use App\Http\Controllers\Masterlist\Status\AssetStatusController;
use App\Http\Controllers\Masterlist\Status\CycleCountStatusController;
use App\Http\Controllers\Masterlist\Status\DepreciationStatusController;
use App\Http\Controllers\Masterlist\Status\MovementStatusController;
use App\Http\Controllers\Masterlist\SubCapexController;
use App\Http\Controllers\Masterlist\SupplierController;
use App\Http\Controllers\Masterlist\TypeOfRequestController;
use App\Http\Controllers\Masterlist\UnitOfMeasureController;
use App\Http\Controllers\Masterlist\WarehouseController;
use App\Http\Controllers\Setup\PrinterIPController;
use App\Http\Controllers\Setup\RoleManagementController;
use App\Http\Controllers\Setup\SetupController;
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

// Route::POST('setup/department', [SetupController::class, 'createDepartment']);

// Route::RESOURCE('user', UserController::class);

Route::POST('/auth/login', [AuthController::class, 'Login']);
// Route::RESOURCE('user', UserController::class);

//DOWNLOAD SAMPLE FILE//
Route::GET('capex-sample-file', [CapexController::class, 'sampleCapexDownload']);
Route::GET('fixed-asset-sample-file', [FixedAssetController::class, 'sampleFixedAssetDownload']);
Route::GET('additional-cost-sample-file', [AdditionalCostController::class, 'sampleAdditionalCostDownload']);
Route::GET('dl', [AssetRequestController::class, 'downloadAttachments']);
Route::GET('transfer-attachment/{transferNumber}', [AssetTransferRequestController::class, 'transferAttachmentDl']);

Route::GET('getIP', [PrinterIpController::class, 'getClientIP']);
//Route::POST('auth/logout', [AuthController::class, 'Logout'])->middleware('auth:sanctum');
//Route::GET('notification-count', [AuthController::class, 'notificationCount'])->middleware('auth:sanctum');

Route::group(['middleware' => ['auth:sanctum']], function () {

    //ROLE MANAGEMENT
    Route::POST('setup/role', [SetupController::class, 'createRole']);
    Route::RESOURCE('role-management', RoleManagementController::class);
    Route::PUT('role-management/archived-role-management/{id}', [RoleManagementController::class, 'archived']);
    Route::GET('search/role-management', [RoleManagementController::class, 'search']);

    //SETUP//
    Route::POST('setup/module', [SetupController::class, 'createModule']);
    Route::GET('setup/GET-modules', [SetupController::class, 'getModule']);
    Route::PUT('setup/GET-modules/archived-modules/{id}', [SetupController::class, 'archived']);
    Route::GET('setup/getById/{id}', [SetupController::class, 'getModuleId']);
    Route::PUT('setup/update-modules/{id}', [SetupController::class, 'updateModule']);

    //COMPANY//
    Route::RESOURCE('company', CompanyController::class);
    Route::GET('companies/search', [CompanyController::class, 'search']);

    //BUSINESS UNIT//
    Route::RESOURCE('business-unit', BusinessUnitController::class);

    //DEPARTMENT//
    Route::RESOURCE('department', DepartmentController::class);
    Route::GET('departments/search', [DepartmentController::class, 'search']);

    //UNIT//
    Route::RESOURCE('unit', UnitController::class);

    //SUB UNIT//
    Route::RESOURCE('sub-unit', SubUnitController::class);
    Route::PATCH('archived-sub-unit/{id}', [SubUnitController::class, 'archived']);

    //LOCATION//
    Route::RESOURCE('location', LocationController::class);
    Route::GET('locations/search', [LocationController::class, 'search']);

    //ACCOUNT TITLE//
    Route::RESOURCE('account-title', AccountTitleController::class);
    Route::GET('account-titles/search', [AccountTitleController::class, 'search']);
    Route::PATCH('account-title/archived-account-title/{id}', [AccountTitleController::class, 'archived']);

    //SUPPLIER//
    Route::RESOURCE('supplier', SupplierController::class);

    //UNIT OF MEASURE
    Route::RESOURCE('uom', UnitOfMeasureController::class);

    ///AUTH//
    Route::PUT('auth/reset/{id}', [AuthController::class, 'resetPassword']);
    Route::POST('auth/change_password', [AuthController::class, 'changedPassword']);
    Route::POST('auth/logout', [AuthController::class, 'Logout']);

    //USER//
    Route::RESOURCE('user', UserController::class);
    Route::GET('users/search', [UserController::class, 'search']);
    Route::PUT('user/archived-user/{id}', [UserController::class, 'archived']);
    Route::GET('test', [UserController::class, 'test']);

    //ServiceProvider
    //    Route::RESOURCE('service-provider', ServiceProviderController::class);
    //    Route::PUT('service-provider/archived-service-provider/{id}', [ServiceProviderController::class, 'archived']);
    //    Route::GET('service-providers/search', [ServiceProviderController::class, 'search']);

    //MAJOR CATEGORY//
    Route::RESOURCE('major-category', MajorCategoryController::class);
    Route::PUT('major-category/archived-major-category/{id}', [MajorCategoryController::class, 'archived']);
    Route::GET('major-categories/search', [MajorCategoryController::class, 'search']);

    //MINOR CATEGORY//
    Route::RESOURCE('minor-category', MinorCategoryController::class);
    Route::PUT('minor-category/archived-minor-category/{id}', [MinorCategoryController::class, 'archived']);
    Route::GET('minor-categories/search', [MinorCategoryController::class, 'search']);

//    Route::RESOURCE('category-list', CategoryListController::class);
    //    Route::PUT('category-list/archived-category-list/{id}', [CategoryListController::class, 'archived']);
    //    Route::GET('category-lists/search', [CategoryListController::class, 'search']);
    //    Route::PUT('category-list/add-update-minorcategory/{id}', [CategoryListController::class, 'UpdateMinorCategory']);

    Route::RESOURCE('supplier', SupplierController::class);
    Route::PUT('supplier/archived-supplier/{id}', [SupplierController::class, 'archived']);
    Route::GET('suppliers/search', [SupplierController::class, 'search']);

    //DIVISION//
    Route::RESOURCE('division', DivisionController::class);
    Route::PUT('division/archived-division/{id}', [DivisionController::class, 'archived']);
    Route::GET('divisions/search', [DivisionController::class, 'search']);

    //CAPEX//
    Route::RESOURCE('capex', CapexController::class);
    Route::PATCH('capex/archived-capex/{id}', [CapexController::class, 'archived']);
    Route::POST('sub_capex/{id}', [CapexController::class, 'storeSubCapex']);
    Route::GET('capex-export', [CapexController::class, 'capexExport']);
    //SUB CAPEX//
    Route::RESOURCE('sub-capex', SubCapexController::class);
    Route::PATCH('sub-capex/archived-sub-capex/{id}', [SubCapexController::class, 'archived']);

    //MASTERLIST IMPORT//
    Route::POST('import-masterlist', [FixedAssetImportController::class, 'masterlistImport']);
    //MASTERLIST EXPORT//
    Route::GET('export-masterlist', [FixedAssetExportController::class, 'export']);
    //CAPEX IMPORT//
    Route::POST('import-capex', [CapexController::class, 'capexImport']);

    //FIXED ASSET//
    Route::RESOURCE('fixed-asset', FixedAssetController::class);
    Route::PATCH('fixed-asset/archived-fixed-asset/{id}', [FixedAssetController::class, 'archived']);
    Route::GET('fixed-asset-search', [FixedAssetController::class, 'search']);
    Route::GET('fixed-assets/search-asset-tag', [FixedAssetController::class, 'searchAssetTag']);
    //ADDITIONAL COST//
    Route::RESOURCE('additional-cost', AdditionalCostController::class);
    Route::POST('add-cost-depreciation/{id}', [AdditionalCostController::class, 'assetDepreciation']);
    Route::PATCH('add-cost/archived-add-cost/{id}', [AdditionalCostController::class, 'archived']);
    Route::POST('import-add-cost', [AdditionalCostController::class, 'additionalCostImport']);
    //FISTO VOUCHER
    Route::GET('fisto-voucher', [FixedAssetController::class, 'getVoucher']);

    //CUSTOM ASSET DEPRECIATION CALCULATION//
    Route::POST('asset-depreciation/{id}', [FixedAssetController::class, 'assetDepreciation']);
    Route::GET('show-fixed-asset/{tagNumber}', [FixedAssetController::class, 'showTagNumber']);

    //BARCODE//
    Route::POST('fixed-asset/barcode', [PrintBarCodeController::class, 'printBarcode']);
    Route::GET('print-barcode-show', [PrintBarCodeController::class, 'viewSearchPrint']);

    //TYPE OF REQUEST//
    Route::RESOURCE('type-of-request', TypeOfRequestController::class);
    Route::PATCH('type-of-request/archived-tor/{id}', [TypeOfRequestController::class, 'archived']);

    //PRINT IP//
    Route::RESOURCE('printer-ip', PrinterIpController::class);
    Route::PATCH('activateIp/{id}', [PrinterIpController::class, 'activateIP']);
    //    Route::GET('getIP', [PrinterIpController::class, 'getClientIP']);

    //STATUSES//
    //ASSET STATUS
    Route::RESOURCE('asset-status', AssetStatusController::class);
    Route::PATCH('asset-status/archived-asset-status/{id}', [AssetStatusController::class, 'archived']);
    //CYCLE COUNT STATUS
    Route::RESOURCE('cycle-count-status', CycleCountStatusController::class);
    Route::PATCH('cycle-count-status/archived-cycle-count-status/{id}', [CycleCountStatusController::class, 'archived']);
    //DEPRECIATION STATUS
    Route::RESOURCE('depreciation-status', DepreciationStatusController::class);
    Route::PATCH('depreciation-status/archived-depreciation-status/{id}', [DepreciationStatusController::class, 'archived']);
    //MOVEMENT STATUS
    Route::RESOURCE('movement-status', MovementStatusController::class);
    Route::PATCH('movement-status/archived-movement-status/{id}', [MovementStatusController::class, 'archived']);

    //APPROVER SETTING//
    Route::RESOURCE('approver-setting', ApproverSettingController::class);
    Route::GET('setup-approver', [ApproverSettingController::class, 'approverSetting']);
    Route::PATCH('approver-setting/archived-approver-setting/{id}', [ApproverSettingController::class, 'archived']);

    //ASSIGNING APPROVER//
    //    Route::RESOURCE('assign-approver', AssignApproverController::class);
    //    Route::GET('requester-view', [AssignApproverController::class, 'requesterView']);
    //Route::PUT('arrange-layer/{id}', [AssignApproverController::class, 'arrangeLayer']);
    Route::PUT('arrange-layer/{id}', [DepartmentUnitApproversController::class, 'arrangeLayer']);

    //ASSET REQUEST//
    Route::RESOURCE('asset-request', AssetRequestController::class);
    Route::POST('update-request/{referenceNumber}', [AssetRequestController::class, 'updateRequest']);
    Route::DELETE('delete-request/{transactionNumber}/{referenceNumber?}', [AssetRequestController::class, 'removeRequestItem']);
    Route::PATCH('resubmit-request', [AssetRequestController::class, 'resubmitRequest']);
    Route::POST('move-to-asset-request', [AssetRequestController::class, 'moveData']);
    Route::GET('show-by-id/{id}', [AssetRequestController::class, 'showById']);
    Route::GET('per-request/{transaction_number}', [AssetRequestController::class, 'getPerRequest']);
    //ASSET APPROVAL//
    Route::RESOURCE('asset-approval', AssetApprovalController::class);
    // Route::GET('asset-approvals/{transactionNumber}', [AssetApprovalController::class, 'showtest']);
    Route::PATCH('handle-request', [AssetApprovalController::class, 'handleRequest']);
    Route::GET('next-request', [AssetApprovalController::class, 'getNextRequest']);
    Route::GET('next-transfer', [AssetTransferApprovalController::class, 'getNextTransferRequest']);
    //APPROVAL LOGGER//
    Route::RESOURCE('approval-logs', AssetApprovalLoggerController::class);
    //DEPARTMENT UNIT APPROVER LAYER SETUP//
    Route::RESOURCE('department-unit-approvers', DepartmentUnitApproversController::class);
    Route::PUT('arrange-layer/{id}', [DepartmentUnitApproversController::class, 'arrangeLayer']);
    //ADDING PR//
    Route::RESOURCE('adding-pr', AddingPrController::class);
    Route::PUT('remove-pr/{transactionNumber}', [AddingPrController::class, 'removePR']);
    //REQUEST CONTAINER//
    Route::RESOURCE('request-container', RequestContainerController::class);
    // ->middleware('normalizeInput');
    Route::DELETE('remove-container-item/{id?}', [RequestContainerController::class, 'removeAll']);
    Route::POST('update-container/{id}', [RequestContainerController::class, 'updateContainer']);
    //ADDING PO//
    Route::RESOURCE('adding-po', AddingPoController::class);
    Route::post('ymir-po-receiving', [AddingPoController::class, 'handleSyncData']);
    //RELEASE ASSET//
    Route::RESOURCE('asset-release', AssetReleaseController::class);
    Route::PUT('release-asset', [AssetReleaseController::class, 'releaseAssets']);
    Route::PUT('update-release-accountability', [AssetReleaseController::class, 'updateAccountability']);
    //NOTIFICATION COUNT//
    Route::GET('notification-count', [AuthController::class, 'notificationCount']);

    Route::GET('item-detail/{referenceNumber?}', [AssetRequestController::class, 'getItemDetails'])->name('item-detail');

    //AssetTransferApprover
    Route::RESOURCE('asset-transfer-approver', AssetTransferApproverController::class);
    Route::PUT('update-transfer-approver/{id}', [AssetTransferApproverController::class, 'arrangeLayer']);

    Route::RESOURCE('asset-pullout-approver', AssetPullOutApproverController::class);
    Route::PUT('update-pullout-approver/{id}', [AssetPullOutApproverController::class, 'arrangeLayer']);

    Route::RESOURCE('asset-disposal-approver', AssetDisposalApproverController::class);
    Route::PUT('update-disposal-approver/{id}', [AssetDisposalApproverController::class, 'arrangeLayer']);

    //ASSET TRANSFER
    //    Route::RESOURCE('asset-transfer', AssetTransferController::class);

    //ASSET TRANSFER CONTAINER
    Route::RESOURCE('asset-transfer-container', AssetTransferContainerController::class)->except(['destroy']);
    Route::DELETE('asset-transfer-container/{id?}', [AssetTransferContainerController::class, 'destroy']);

    //ASSET TRANSFER
    Route::RESOURCE('asset-transfer', AssetTransferRequestController::class);
    Route::POST('move-to-asset-transfer', [AssetTransferRequestController::class, 'transferContainerData']);
    Route::POST('update-transfer-request/{transferNumber}', [AssetTransferRequestController::class, 'updateTransfer']);
    Route::DELETE('remove-transfer-item/{transferNumber?}/{id?}', [AssetTransferRequestController::class, 'removedTransferItem']);

    Route::POST('testmorp', [AssetTransferRequestController::class, 'testmorp']);

    //ASSET TRANSFER APPROVAL
    Route::RESOURCE('transfer-approver', AssetTransferApprovalController::class);
    Route::PATCH('transfer-approval', [AssetTransferApprovalController::class, 'transferRequestAction']);

    //WAREHOUSE MASTERLIST
    Route::RESOURCE('warehouse', WarehouseController::class);
    Route::PATCH('warehouse/archived-warehouse/{id}', [WarehouseController::class, 'archived']);

    //PRINTING MEMO
    Route::PUT('memo-print', [MemoSeriesController::class, 'memoPrint']);
    Route::GET('reprint-memo', [MemoSeriesController::class, 'memoReprint']);

    //SMALL TOOLS
    Route::put('inclusion', [FixedAssetController::class, 'inclusions']);
    Route::patch('remove-inclusions', [FixedAssetController::class, 'removeInclusionItem']);

    //MEMO SERIES
//    Route::GET('get-memo-series', [MemoSeriesController::class, 'getMemoSeries']);
//    Route::PATCH('archived-memo-series/{id}', [MemoSeriesController::class, 'archived']);

    Route::GET('post-to-ymir', [AddingPrController::class, 'sendToYmir']);
    Route::RESOURCE('test-transfer', TransferController::class);

    Route::POST('addcost-test', [AdditionalCostController::class,'syncData']);

    //CANCEL RR NUMBER
    Route::RESOURCE('rr-summary', ReceiveReceiptSummaryController::class);
    Route::PATCH('cancel-rr/{rrNumber}', [ReceiveReceiptSummaryController::class, 'cancelledRR']);

    Route::prefix('ymir')->group(function () {
        Route::GET('pr-request', [AddingPrController::class, 'requestToPR']);
        Route::PATCH('pr-return', [AddingPrController::class, 'returnFromYmir']);
    });
});

<?php

use App\Http\Controllers\API\AddingPoController;
use App\Http\Controllers\API\AddingPrController;
use App\Http\Controllers\API\ApproverSettingController;
use App\Http\Controllers\API\Approvers\AssetDisposalApproverController;
use App\Http\Controllers\API\Approvers\AssetPullOutApproverController;
use App\Http\Controllers\API\Approvers\AssetTransferApproverController;
use App\Http\Controllers\API\AssetApprovalController;
use App\Http\Controllers\API\AssetApprovalLogger\AssetApprovalLoggerController;
use App\Http\Controllers\API\AssetMovement\PullOutController;
use App\Http\Controllers\API\AssetMovement\Transfer\AssetTransferApprovalController;
use App\Http\Controllers\API\AssetMovement\Transfer\AssetTransferContainerController;
use App\Http\Controllers\API\AssetMovement\Transfer\AssetTransferRequestController;
use App\Http\Controllers\API\AssetMovement\TransferController;
use App\Http\Controllers\API\AssetReleaseController;
use App\Http\Controllers\API\AssetRequestController;
use App\Http\Controllers\API\PrRecon\PrReconController;
use App\Http\Controllers\API\ReceiveReceiptSummaryController;
use App\Http\Controllers\API\DepartmentUnitApproversController;
use App\Http\Controllers\API\ReplacementSmallToolController;
use App\Http\Controllers\API\RequestContainerController;
use App\Http\Controllers\API\SmallToolSelectionController;
use App\Http\Controllers\AssetMovementBaseController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\Masterlist\AdditionalCostController;
use App\Http\Controllers\Masterlist\CapexController;
use App\Http\Controllers\Masterlist\COA\AccountTitleController;
use App\Http\Controllers\Masterlist\COA\BusinessUnitController;
use App\Http\Controllers\Masterlist\COA\CompanyController;
use App\Http\Controllers\Masterlist\COA\CreditController;
use App\Http\Controllers\Masterlist\COA\DepartmentController;
use App\Http\Controllers\Masterlist\COA\LocationController;
use App\Http\Controllers\Masterlist\COA\SubUnitController;
use App\Http\Controllers\Masterlist\COA\UnitController;
use App\Http\Controllers\Masterlist\DepreciationHistoryController;
use App\Http\Controllers\Masterlist\DivisionController;
use App\Http\Controllers\Masterlist\FixedAssetController;
use App\Http\Controllers\Masterlist\FixedAssetExportController;
use App\Http\Controllers\Masterlist\FixedAssetImportController;
use App\Http\Controllers\Masterlist\ItemController;
use App\Http\Controllers\Masterlist\MajorCategoryController;
use App\Http\Controllers\Masterlist\MinorCategoryController;
use App\Http\Controllers\Masterlist\PrintBarcode\MemoSeriesController;
use App\Http\Controllers\Masterlist\PrintBarcode\PrintBarCodeController;
use App\Http\Controllers\Masterlist\SmallToolsController;
use App\Http\Controllers\Masterlist\Status\AssetStatusController;
use App\Http\Controllers\Masterlist\Status\CycleCountStatusController;
use App\Http\Controllers\Masterlist\Status\DepreciationStatusController;
use App\Http\Controllers\Masterlist\Status\MovementStatusController;
use App\Http\Controllers\Masterlist\SubCapexController;
use App\Http\Controllers\Masterlist\SupplierController;
use App\Http\Controllers\Masterlist\TypeOfRequestController;
use App\Http\Controllers\Masterlist\UnitOfMeasureController;
use App\Http\Controllers\Masterlist\WarehouseController;
use App\Http\Controllers\Setup\APIKeyGenerationController;
use App\Http\Controllers\Setup\ApiTokenController;
use App\Http\Controllers\Setup\AuthorizedTransferReceiverController;
use App\Http\Controllers\Setup\CoordinatorHandleController;
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
Route::GET('transfer-attachment/{transferNumber}', [AssetTransferRequestController::class, 'transferAttachmentDl']);// old transfer download
Route::GET('transfer-download/{movementId}', [TransferController::class, 'movementMediaDownload']);

Route::GET('getIP', [PrinterIPController::class, 'getClientIP']);
//Route::POST('auth/logout', [AuthController::class, 'Logout'])->middleware('auth:sanctum');
//Route::GET('notification-count', [AuthController::class, 'notificationCount'])->middleware('auth:sanctum');
Route::prefix('report')->middleware('api.key')->group(function () {
    Route::GET('gl-report', [FixedAssetController::class, 'reportGL']);
});
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
    Route::PUT('account-title/depreciation-debit_tagging/{id}', [AccountTitleController::class, 'depreciationDebitTagging']);

    //CREDIT//
    Route::RESOURCE('credit', CreditController::class);

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

    //COA UPDATE IMPORT//
    Route::POST('import-coa-update', [FixedAssetImportController::class, 'CoaUpdateImport']);

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
    Route::GET('add-cost-depreciation/{id}', [AdditionalCostController::class, 'assetDepreciation']);
    Route::PATCH('add-cost/archived-add-cost/{id}', [AdditionalCostController::class, 'archived']);
    Route::POST('import-add-cost', [AdditionalCostController::class, 'additionalCostImport']);
    Route::POST('asset-to-addcost', [AdditionalCostController::class, 'addToAddCost']);
    //UPDATE DESCRIPTION ONLY
    Route::PATCH('fixed-asset/update-description/{id}', [FixedAssetController::class, 'updateDescription']);

    //FISTO VOUCHER
    Route::GET('fisto-voucher', [FixedAssetController::class, 'getVoucher']);

    //CUSTOM ASSET DEPRECIATION CALCULATION//
    Route::GET('asset-depreciation/{id}', [FixedAssetController::class, 'assetDepreciation']);
    Route::GET('show-fixed-asset/{tagNumber}', [FixedAssetController::class, 'showTagNumber']);
    Route::GET('next-to-depreciate', [FixedAssetController::class, 'nextToDepreciate']);

    //BARCODE//
    Route::POST('fixed-asset/barcode', [PrintBarCodeController::class, 'printBarcode']);
    Route::GET('print-barcode-show', [PrintBarCodeController::class, 'viewSearchPrint']);

    //SMALL TOOLS MAIN ASSET SELECTION//
    Route::POST('small-tools-main-asset', [SmallToolSelectionController::class, 'selectMainAsset']);
    Route::PUT('ungroup-small-tools-main-asset/{id}', [SmallToolSelectionController::class, 'unGroupAsset']);
    Route::PUT('small-tools-main-asset/update/{id}', [SmallToolSelectionController::class, 'updateChildAsset']);
    Route::patch('small-tools-main-asset/remove/{id}', [SmallToolSelectionController::class, 'removeChildAsset']);
    Route::PUT('small-tools-main-asset/not-printable', [SmallToolSelectionController::class, 'setNotPrintableSmallTools']);

    //TYPE OF REQUEST//
    Route::RESOURCE('type-of-request', TypeOfRequestController::class);
    Route::PATCH('type-of-request/archived-tor/{id}', [TypeOfRequestController::class, 'archived']);

    //PRINT IP//
    Route::RESOURCE('printer-ip', PrinterIPController::class);
    Route::PATCH('activateIp/{id}', [PrinterIPController::class, 'activateIP']);
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

    //COORDINATOR HANDLES
    Route::RESOURCE('coordinator-handles', CoordinatorHandleController::class);
    Route::PATCH('archived-coordinator-handles/{id}', [CoordinatorHandleController::class, 'archived']);

    //AUTHORIZED TRANSFER RECEIVER
    Route::RESOURCE('authorized-transfer-receiver', AuthorizedTransferReceiverController::class);
    Route::PATCH('archived-authorized-transfer-receiver/{id}', [AuthorizedTransferReceiverController::class, 'archived']);

    //ASSIGNING APPROVER//
    //    Route::RESOURCE('assign-approver', AssignApproverController::class);
    //    Route::GET('requester-view', [AssignApproverController::class, 'requesterView']);
    //Route::PUT('arrange-layer/{id}', [AssignApproverController::class, 'arrangeLayer']);
    Route::PUT('arrange-layer/{id}', [DepartmentUnitApproversController::class, 'arrangeLayer']);

    //ASSET REQUEST//
    Route::RESOURCE('asset-request', AssetRequestController::class);
    Route::GET('asset-request-test', [AssetRequestController::class, 'indexRevamp']);

    Route::POST('update-request/{referenceNumber}', [AssetRequestController::class, 'updateRequest'])->name('update-request');
    Route::DELETE('delete-request/{transactionNumber}/{referenceNumber?}', [AssetRequestController::class, 'removeRequestItem']);
    Route::PATCH('resubmit-request', [AssetRequestController::class, 'resubmitRequest']);
    Route::POST('move-to-asset-request', [AssetRequestController::class, 'moveData']);
    Route::GET('show-by-id/{id}', [AssetRequestController::class, 'showById']);
    Route::GET('per-request/{transaction_number}', [AssetRequestController::class, 'getPerRequest']);
    Route::GET('export-aging', [AssetRequestController::class, 'exportAging']);
    //ASSET APPROVAL//
    Route::RESOURCE('asset-approval', AssetApprovalController::class);
    // Route::GET('asset-approvals/{transactionNumber}', [AssetApprovalController::class, 'showtest']);
    Route::PATCH('handle-request', [AssetApprovalController::class, 'handleRequest']);
    Route::GET('next-request', [AssetApprovalController::class, 'getNextRequest']);
    Route::GET('next-transfer', [AssetTransferApprovalController::class, 'getNextTransferRequest']);
    Route::PUT('final-approval-update/{referenceNumber}', [AssetApprovalController::class, 'finalApprovalUpdate']);
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
    Route::POST('update-container/{id}', [RequestContainerController::class, 'updateContainer'])->name('update-container');
    //ADDING PO//
    Route::RESOURCE('adding-po', AddingPoController::class);
    Route::POST('ymir-po-receiving', [AddingPoController::class, 'handleSyncData']);
    Route::PATCH('cancel-remaining', [AddingPoController::class, 'cancelRemaining']);
    Route::PATCH('po-added', [AddingPoController::class, 'poCreatedActivity']);
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


    //ASSET TRANSFER APPROVAL
//    Route::RESOURCE('transfer-approver', AssetTransferApprovalController::class);
//    Route::PATCH('transfer-approval', [AssetTransferApprovalController::class, 'transferRequestAction']);

    //WAREHOUSE MASTERLIST
    Route::RESOURCE('warehouse', WarehouseController::class);
    Route::PATCH('warehouse/archived-warehouse/{id}', [WarehouseController::class, 'archived']);
    Route::PUT('warehouse/location-tagging/{id}', [WarehouseController::class, 'locationTagging']);
    Route::PUT('warehouse/department-tagging/{id}', [WarehouseController::class, 'departmentTagging']);

    //PRINTING MEMO
    Route::PUT('memo-print', [MemoSeriesController::class, 'memoPrint']);
    Route::GET('reprint-memo', [MemoSeriesController::class, 'memoReprint']);
    Route::GET('series-data/{id}', [MemoSeriesController::class, 'printData']);

    //SMALL TOOLS
    Route::put('inclusion', [FixedAssetController::class, 'inclusions']);
    Route::patch('remove-inclusion', [FixedAssetController::class, 'removeInclusionItem']); //remove s

    //REPLACEMENT SMALL TOOLS RELEASING
//    Route::RESOURCE('small-tools-replacement-release', ReplacementSmallToolController::class);
//    Route::PUT('release-small-tool', [ReplacementSmallToolController::class, 'releaseSmallToolReplacement']);
    Route::PUT('update-small-tool-item/{id}', [ReplacementSmallToolController::class, 'smallToolItemUpdate']);

    //MEMO SERIES
//    Route::GET('get-memo-series', [MemoSeriesController::class, 'getMemoSeries']);
//    Route::PATCH('archived-memo-series/{id}', [MemoSeriesController::class, 'archived']);

    Route::GET('post-to-ymir', [AddingPrController::class, 'sendToYmir']);

    //NEW TRANSFER
    Route::RESOURCE('transfer', TransferController::class);
    Route::GET('transfer-approver', [TransferController::class, 'approvalViewing']); //transfer-approval
    Route::GET('get-next-transfer', [TransferController::class, 'nextToApproved']);
    Route::PATCH('handle-transfer-movement', [TransferController::class, 'handleMovement']);
    Route::POST('transfer-update/{movementId}', [TransferController::class, 'movementUpdate'])->name('transfer-update');
//    Route::GET('transfer-download/{movementId}', [TransferController::class, 'movementMediaDownload']);
    Route::PATCH('void-transfer', [TransferController::class, 'voidMovement']);
    Route::PATCH('received-confirmation', [TransferController::class, 'movementConfirmation']);
    Route::GET('transfer-receiver', [TransferController::class, 'movementReceiverViewing']);
    Route::GET('show-receiving/{transferId}', [TransferController::class, 'singleViewing']);
    Route::PATCH('update-depreciation/{movementId}', [TransferController::class, 'editDepreciationDebit']);
    Route::PATCH('reject-transfer', [TransferController::class, 'rejectItem']);

    //NEW PULLOUT
    Route::RESOURCE('pullout', PullOutController::class);
    Route::GET('pullout-approver', [PullOutController::class, 'approvalViewing']); //transfer-approval
    Route::PATCH('handle-pullout-movement', [PullOutController::class, 'handleMovement']);
    Route::GET('get-next-pullout', [PullOutController::class, 'nextToApproved']);
    Route::POST('pullout-update/{movementId}', [PullOutController::class, 'movementUpdate'])->name('pullout-update');
    Route::PATCH('void-pullout', [PullOutController::class, 'voidMovement']);
    //FOR HM AND ME
    Route::GET('item-to-pullout', [PullOutController::class, 'toPullOutViewing']);
    Route::GET('item-to-pullout-show/{movementId}', [PullOutController::class, 'toPullOutShow']);
    Route::PATCH('pick-up/{movementId}', [PullOutController::class, 'pickedUpConfirmation']);
    Route::GET('items-to-evaluate', [PullOutController::class, 'listOfItemsToEvaluate']);
    Route::POST('evaluate-pullout', [PullOutController::class, 'evaluateItems']);
    //AFTER EVALUATION APPROVAL
    Route::GET('evaluation-approval', [PullOutController::class, 'itemApprovalView']);


    //MOVEMENT HISTORY REPORTS
    Route::GET('movement-history', [FixedAssetController::class, 'movementReports']);


    //ELIXIR ADDITIONAL COST
    Route::POST('addcost-sync', [AdditionalCostController::class, 'syncData']);
    Route::POST('addcost-tagging', [AdditionalCostController::class, 'tagToAsset']);

    //CANCEL RR NUMBER
    Route::RESOURCE('rr-summary', ReceiveReceiptSummaryController::class);
    Route::PATCH('cancel-rr/{rrNumber}', [ReceiveReceiptSummaryController::class, 'cancelledRR']);

    //DEPRECIATION HISTORY
    Route::GET('depreciation_history/{vTagNumber}', [DepreciationHistoryController::class, 'showHistory']); //todo change this to use this -
    Route::GET('depreciation-report', [DepreciationHistoryController::class, 'monthlyDepreciationReport']);

    Route::GET('pr-report', [AddingPrController::class, 'prReport']);


    //Fixed Asset Click to depreciate
    Route::POST('depreciate/{tagNumber}', [FixedAssetController::class, 'accountingEntriesInput']);

    //SMALL TOOLS
//    Route::RESOURCE('small-tools', SmallToolsController::class);
//    Route::RESOURCE('item', ItemController::class);


    //API TOKEN
    Route::RESOURCE('api-token', ApiTokenController::class);
    Route::GET('get-token/{projectName}', [ApiTokenController::class, 'getToken']);
    Route::PATCH('archived-api-token/{id}', [ApiTokenController::class, 'archived']);

    //YMIR GET ALL FA
    Route::GET('ymir-fa', [FixedAssetController::class, 'ymirFixedAsset']);


//    ->middleware('api.key')

    Route::get('generate-api-key', [APIKeyGenerationController::class, 'generateAPIKey']);

    Route::prefix('ymir')->group(function () {
        Route::GET('pr-request', [AddingPrController::class, 'requestToPR']);
        Route::PATCH('pr-return', [AddingPrController::class, 'returnFromYmir']);
    });

    Route::prefix('recon')->group(function () {
        Route::GET('pr-recon', [PrReconController::class, 'prReconViewing']);
    });

    //Testing Route Area
    Route::POST('testmorp', [AssetTransferRequestController::class, 'testmorp']);
    Route::GET('new-depreciation-test/{id}', [FixedAssetController::class, 'depreciation']);

    Route::GET('depreciationasset/{id}', [FixedAssetController::class, 'depreciationViewing']);


    Route::POST('sending-attachments', [AddingPrController::class, 'sendTransactionWithAttachments']);
    Route::Patch('testpocreate', [AddingPoController::class, 'clientTest']);

});

Route::GET('file', [FileController::class, 'show']);


//middleware('auth:sanctum')->
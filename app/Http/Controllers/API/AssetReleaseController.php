<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssetRelease\MultipleReleaseRequest;
use App\Http\Requests\AssetRelease\UpdateAssetReleaseRequest;
use App\Models\AdditionalCost;
use App\Models\AssetRequest;
use App\Models\FixedAsset;
use App\Models\Status\DepreciationStatus;
use App\Repositories\CalculationRepository;
use App\Repositories\FixedAssetRepository;
use App\Repositories\VladimirTagGeneratorRepository;
use App\Traits\AssetReleaseHandler;
use App\Traits\AssetRequestHandler;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssetReleaseController extends Controller
{
    use ApiResponse, AssetReleaseHandler;


    public function index(Request $request)
    {
        $search = $request->get('search');
        $per_page = $request->get('per_page');
        $page = $request->get('page');
        $isReleased = $request->get('isReleased');

        if ($per_page == null) {
            $fixed_assets = FixedAsset::where('can_release', 1)->where('from_request', 1)->orderByDesc('created_at')
                ->get();
            return $this->transformFixedAsset($fixed_assets);
        } else {
            return $this->searchFixedAsset($search, $page, $isReleased, $per_page);
        }
    }

    public function show(int $warehouseId)
    {
        $fixed_asset = FixedAsset::where('warehouse_number_id', $warehouseId)->first();
        $additionalCost = AdditionalCost::where('warehouse_number_id', $warehouseId)->first();

        if (!$fixed_asset && !$additionalCost) {
            return $this->responseNotFound('Asset Not Found');
        }

        if ($additionalCost) {
            return $this->transformSingleAdditionalCost($additionalCost);
        }

        return $this->transformSingleFixedAsset($fixed_asset);
    }

    public function releaseAssets(MultipleReleaseRequest $request)
    {
//        return $request->all();
        $warehouseIds = $request->get('warehouse_number_id');
        $accountability = $request->get('accountability');
        $accountable = $request->get('accountable');
        $receivedBy = $request->get('received_by');
        $signature = $request->get('signature') ?? null;
        $companyId = $request->get('company_id');
        $businessUnitId = $request->get('business_unit_id');
        $departmentId = $request->get('department_id');
        $unitId = $request->get('unit_id');
        $subunitId = $request->get('subunit_id');
        $locationId = $request->get('location_id');
        $accountTitleId = $request->get('account_title_id');
        $depreciation = DepreciationStatus::where('depreciation_status_name', 'For Depreciation')->first()->id;
        foreach ($warehouseIds as $warehouseId) {

            $fixedAssetQuery = FixedAsset::where('warehouse_number_id', $warehouseId)->where('is_released', 0);
            $fixedAssetCount = (clone $fixedAssetQuery)->count();

            $additionalCostQuery = AdditionalCost::where('warehouse_number_id', $warehouseId)->where('is_released', 0);
            $additionalCostCount = (clone $additionalCostQuery)->count();

            if ($fixedAssetCount == 0 && $additionalCostCount == 0) {
                return $this->responseNotFound('Asset Not Found');
            }


            if ($fixedAssetCount > 0) {
                $processedAsset = $this->processAsset($fixedAssetQuery, $signature, $receivedBy, $accountability, $accountable, $depreciation, $companyId, $businessUnitId, $departmentId, $unitId, $subunitId, $locationId, $accountTitleId);
            }

            if ($additionalCostCount > 0) {
                $processedAsset = $this->processAsset($additionalCostQuery, $signature, $receivedBy, $accountability, $accountable, $depreciation, $companyId, $businessUnitId, $departmentId, $unitId, $subunitId, $locationId, $accountTitleId);
            }

            if ($processedAsset) {
                $transactionNumber = $processedAsset->transaction_number;
                $unreleasedFixedAssets = FixedAsset::where('transaction_number', $transactionNumber)->where('is_released', 0)->count();
                $unreleasedAdditionalCosts = AdditionalCost::where('transaction_number', $transactionNumber)->where('is_released', 0)->count();

                // If all assets are released, update the asset request
                if ($unreleasedFixedAssets == 0 && $unreleasedAdditionalCosts == 0) {
                    AssetRequest::where('transaction_number', $transactionNumber)->update([
                        'is_claimed' => 1,
                        'filter' => 'Claimed'
                    ]);
                }
            }
        }
        return $this->responseSuccess('Assets Released');
    }


    private function getAssetRequest($fixedAssetQuery, $additionalCostQuery)
    {
//        if ($fixedAssetCount == 1 || $additionalCostCount == 1) {
//                $assetRequest = $this->getAssetRequest($fixedAssetQuery, $additionalCostQuery);
//                if ($this->hasUnreleasedAssets($assetRequest) == 0) {
//                    $this->updateIsClaimed($assetRequest);
//                }
//            }
//

        return AssetRequest::withTrashed()->where(function ($query) use ($fixedAssetQuery, $additionalCostQuery) {
            $fixedAsset = $fixedAssetQuery->first();
            if ($fixedAsset) {
                $query->where('transaction_number', $fixedAsset->transaction_number)
                    ->whereRaw('quantity = quantity_delivered')
                    ->whereRaw('print_count = quantity');
            }
        })->orWhere(function ($query) use ($additionalCostQuery) {
            $additionalCost = $additionalCostQuery->first();
            if ($additionalCost) {
                $query->where('transaction_number', $additionalCost->transaction_number)
                    ->whereRaw('quantity = quantity_delivered');
            }
        });
    }

    private function hasUnreleasedAssets($assetRequest)
    {
        $transactionNumber = $assetRequest->value('transaction_number');
        $unreleasedFixedAssets = FixedAsset::where('transaction_number', $transactionNumber)
            ->where('is_released', 0)
            ->count();
        $unreleasedAdditionalCosts = AdditionalCost::where('transaction_number', $transactionNumber)
            ->where('is_released', 0)
            ->count();

        return $unreleasedFixedAssets > 0 || $unreleasedAdditionalCosts > 0;
    }

    private function updateIsClaimed($assetRequestQuery)
    {
        $assetRequest = $assetRequestQuery->first();
        if ($assetRequest) {
            $assetRequest->update([
                'is_claimed' => 1,
                'filter' => 'Claimed'
            ]);
        }
    }
}

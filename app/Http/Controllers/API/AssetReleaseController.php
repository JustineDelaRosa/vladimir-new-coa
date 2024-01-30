<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssetRelease\MultipleReleaseRequest;
use App\Http\Requests\AssetRelease\UpdateAssetReleaseRequest;
use App\Models\AdditionalCost;
use App\Models\FixedAsset;
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
            $fixed_assets = FixedAsset::where('can_release', 1)->where('from_request', 1)
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

    public function releaseAssets(MultipleReleaseRequest $request): JsonResponse
    {
        $warehouseIds = $request->get('warehouse_number_id');
        $accountability = $request->get('accountability');
        $accountable = $request->get('accountable');
        $receivedBy = $request->get('received_by');

        foreach ($warehouseIds as $warehouseId) {
            $fixedAsset = FixedAsset::where('warehouse_number_id', $warehouseId)->where('is_released', 0)->first();
            $additionalCost = AdditionalCost::where('warehouse_number_id', $warehouseId)->where('is_released', 0)->first();

            if (!$fixedAsset && !$additionalCost) {
                return $this->responseNotFound('Asset Not Found');
            }

            if ($fixedAsset) {
                $fixedAsset->update([
                    'accountability' => $accountability,
                    'accountable' => $accountable,
                    'received_by' => $receivedBy,
                    'is_released' => 1,
                ]);
            }

            if ($additionalCost) {
                $additionalCost->update([
                    'accountability' => $accountability,
                    'accountable' => $accountable,
                    'received_by' => $receivedBy,
                    'is_released' => 1,
                ]);
            }
        }
        return $this->responseSuccess('Assets Released');
    }
}

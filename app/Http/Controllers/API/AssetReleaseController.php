<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssetRelease\MultipleReleaseRequest;
use App\Http\Requests\AssetRelease\UpdateAccountabilityRequest;
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
use Carbon\Carbon;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssetReleaseController extends Controller
{
    use ApiResponse, AssetReleaseHandler;


    public function index(Request $request)
    {
//        return             $query = FixedAsset::where('can_release', 1)->get();
        $search = $request->get('search');
        $per_page = $request->get('per_page');
        $page = $request->get('page');
        $isReleased = $request->get('isReleased');
        $from = $request->get('from');
        $to = $request->get('to');
        $userWarehouseId = auth('sanctum')->user()->warehouse_id;

        //start hours to from and end hour to to
        if ($from && $to) {
            $from = $from ? Carbon::parse($from)->startOfDay() : null;
            $to = $to ? Carbon::parse($to)->endOfDay() : null;
        }
        if ($per_page == null) {
            $fixedAsset = FixedAsset::where('can_release', 1)
                ->where('from_request', 1)
                ->where('is_released', $isReleased)
                ->where('warehouse_id', $userWarehouseId)
//                ->whereHas('warehouse', function ($query) use ($userWarehouseId) {
//                    $query->where('id', $userWarehouseId);
//                })
                ->where(function ($query) {
                    $query->where('accountability', 'Common')
                        ->where('memo_series_id', null)
                        ->orWhere(function ($query) {
                            $query->where('accountability', 'Personal Issued');
//                                ->where('asset_condition', '!=', 'New');
                        })->orWhere(function ($query) {
                            $query->where('accountability', 'Personal Issued')
//                                ->where('asset_condition', 'New')
                                ->whereNotNull('memo_series_id');
                        });
                })->when($from && !$to, function ($query) use ($from) {
                    return $query->where('created_at', '>=', $from);
                })
                ->when(!$from && $to, function ($query) use ($to) {
                    return $query->where('created_at', '<=', $to);
                })->when($from && $to, function ($query) use ($from, $to) {
                    return $query->whereBetween('created_at', [$from, $to]);
                });



            $fixed_assets = $fixedAsset->orderByDesc('created_at')->get();


            $additionalCost = AdditionalCost::where('can_release', 1)
                ->where('from_request', 1)
                ->where('is_released', $isReleased)
                ->where('warehouse_id', $userWarehouseId)
                ->when($from && !$to, function ($query) use ($from) {
                    return $query->where('created_at', '>=', $from);
                })
                ->when(!$from && $to, function ($query) use ($to) {
                    return $query->where('created_at', '<=', $to);
                })->when($from && $to, function ($query) use ($from, $to) {
                    return $query->whereBetween('created_at', [$from, $to]);
                });
////                ->whereHas('warehouse', function ($query) use ($userWarehouseId) {
////                    $query->where('id', $userWarehouseId);
////                })
//                ->where(function ($query) {
//                    $query->where('accountabi
//lity', 'Common')
//                        ->where('memo_series_id', null)
//                        ->orWhere(function ($query) {
//                            $query->where('accountability', 'Personal Issued');
////                                ->where('asset_condition', '!=', 'New');
//                        })->orWhere(function ($query) {
//                            $query->where('accountability', 'Personal Issued')
////                                ->where('asset_condition', 'New')
//                                ->whereNotNull('memo_series_id');
//                        });
//                });

            $additional_cost = $additionalCost->orderByDesc('created_at')->get();

            $response = array_merge(
                $this->transformFixedAsset($fixed_assets),
                $this->transformAdditionalCost($additional_cost)
            );

            return $this->responseSuccess('Assets retrieved successfully', $response);
        }

        return $this->searchFixedAsset($search, $page, $isReleased, $per_page);
    }

    public function updateAccountability(UpdateAccountabilityRequest $request)
    {
        $warehouseIds = $request->get('warehouse_number_id');
        $accountability = $request->get('accountability');
        $accountable = $request->get('accountable');

        DB::beginTransaction();
        try {
            foreach ($warehouseIds as $warehouseId) {
                $fixedAsset = FixedAsset::where('warehouse_number_id', $warehouseId)->first();
                $additionalCost = AdditionalCost::where('warehouse_number_id', $warehouseId)->first();

                if (!$fixedAsset && !$additionalCost) {
                    return $this->responseNotFound('Asset Not Found');
                }

                if ($accountability === 'Common') {
                    $this->updateRemoveMemoTag($fixedAsset, $additionalCost);
                    $this->setNewAccountability($fixedAsset, $additionalCost, $accountability, $accountable, true);
                } else {
                    $this->setNewAccountability($fixedAsset, $additionalCost, $accountability, $accountable);
                }
            }
//            DB::rollBack();
            DB::commit();
            return $this->responseSuccess('Accountability Updated');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->responseUnprocessable('An error occurred while updating accountability: ' . $e->getMessage());
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
        DB::beginTransaction();
        try {
            $warehouseIds = $request->get('warehouse_number_id');
            $accountability = $request->get('accountability');
            $accountable = $request->get('accountable');
            $receivedBy = $request->get('received_by');
//        $signature = $request->get('signature') ?? null;
            $images = [
                'receiverImg' => $request->get('receiver_img') ?? null,
                'assignmentMemoImg' => $request->get('assignment_memo_img') ?? null,
                'authorizationMemoImg' => $request->get('authorization_memo_img') ?? null
            ];
            $companyId = $request->get('company_id');
            $businessUnitId = $request->get('business_unit_id');
            $departmentId = $request->get('department_id');
            $unitId = $request->get('unit_id');
            $subunitId = $request->get('subunit_id');
            $locationId = $request->get('location_id');
//            $accountTitleId = $request->get('account_title_id');

            //todo: will change to Running Depreciation
//            $depreciation = DepreciationStatus::where('depreciation_status_name', 'For Depreciation')->first()->id;
            $depreciation = DepreciationStatus::where('depreciation_status_name', 'For Depreciation')->first()->id;
            foreach ($warehouseIds as $warehouseId) {

                $fixedAssetQuery = FixedAsset::where('warehouse_number_id', $warehouseId)
                    ->where(function ($query) {
                        $query->where('accountability', 'Common')
                            ->where('memo_series_id', null)
                            ->orWhere(function ($query) {
                                $query->where('accountability', 'Personal Issued');
//                                    ->where('asset_condition', '!=', 'New');
                            })->orWhere(function ($query) {
                                $query->where('accountability', 'Personal Issued')
//                                    ->where('asset_condition', 'New')
                                    ->whereNotNull('memo_series_id');
                            });
                    })->where('is_released', 0);

                $fixedAssetCount = (clone $fixedAssetQuery)->count();

                $additionalCostQuery = AdditionalCost::where('warehouse_number_id', $warehouseId)
                    ->where('is_released', 0);

                $additionalCostCount = (clone $additionalCostQuery)->count();

                if ($fixedAssetCount == 0 && $additionalCostCount == 0) {
                    return $this->responseNotFound('Asset Not Found, Check eligibility and try again');
                }

                $processedAsset = null;

                if ($fixedAssetCount > 0) {
                    $processedAsset = $this->processAsset($fixedAssetQuery, $images, $receivedBy, $accountability, $accountable, $depreciation, $companyId, $businessUnitId, $departmentId, $unitId, $subunitId, $locationId);
                }

                if ($additionalCostCount > 0) {
                    $processedAsset = $this->processAsset($additionalCostQuery, $images, $receivedBy, $accountability, $accountable, $depreciation, $companyId, $businessUnitId, $departmentId, $unitId, $subunitId, $locationId);
                }

                if ($processedAsset) {
                    $transactionNumber = $processedAsset->transaction_number;
                    $unreleasedFixedAssets = FixedAsset::where('transaction_number', $transactionNumber)->where('is_released', 0)->count();
                    $unreleasedAdditionalCosts = AdditionalCost::where('transaction_number', $transactionNumber)->where('is_released', 0)->count();

                    // If all assets are released, update the asset request
                    if ($unreleasedFixedAssets == 0 && $unreleasedAdditionalCosts == 0) {
//                        $assetRequest = AssetRequest::where('transaction_number', $transactionNumber)->get();
                        $unreleased = AssetRequest::where('transaction_number', $transactionNumber)
                            ->where('is_asset_small_tool', 0)
                            ->where('is_claimed', 0)
                            ->count();

                        AssetRequest::where('transaction_number', $transactionNumber)->update([
                            'is_claimed' => 1,
//                            'filter' => 'Claimed'
                        ]);


//                        return $this->responseUnprocessable($unreleased);
                        if ($unreleased <= 1) {
                            AssetRequest::where('transaction_number', $transactionNumber)
                                ->whereColumn('quantity', 'quantity_delivered')
//                            ->whereNull('item_id')
                                ->update([
                                    'filter' => 'Claimed'
                                ]);
                        }
                    }
                }
            }
            DB::commit();
            return $this->responseSuccess('Assets Released');
        } catch (\Exception $e) {
            DB::rollBack();
//            return $e->getMessage();
            return $this->responseUnprocessable('An error occurred while releasing assets: ' . $e->getMessage());
        }

    }


    public function getAssetRequest($fixedAssetQuery, $additionalCostQuery)
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

    public function hasUnreleasedAssets($assetRequest)
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

    public function updateIsClaimed($assetRequestQuery)
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

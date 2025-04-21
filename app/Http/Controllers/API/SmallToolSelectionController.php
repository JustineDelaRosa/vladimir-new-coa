<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AssetRequest;
use App\Models\AssetSmallTool;
use App\Models\FixedAsset;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SmallToolSelectionController extends Controller
{
    use ApiResponse;

    public function selectMainAsset(Request $request)
    {
        DB::beginTransaction();
        try {
            $mainAsset = $request->get('main_asset_id');
            $childAsset = $request->get('child_asset_ids', []);

            if (empty($childAsset)) {
                return $this->responseUnprocessable('No child asset selected');
            }

            //check if the child asset is already selected as parent/main asset
            $selectedChildAsset = FixedAsset::whereIn('id', $childAsset)->whereHas('assetSmallTools')->get();
            if (!$selectedChildAsset->isEmpty()) {
                return $this->responseUnprocessable('Child asset is already selected as main asset');
            }

            // Add the child Asset to asset_small_tool table then delete it from fixed_assets table
            $this->addToAssetSmallTool($mainAsset, $childAsset);

            // Delete the child assets from the fixed_assets table
            FixedAsset::whereIn('id', $childAsset)->delete();

            DB::commit();
            return $this->responseSuccess('Successfully selected the main asset');
        } catch (\Exception $e) {
            DB::rollBack();
//            return $e->getMessage();
            return $this->responseUnprocessable('Something went wrong');
        }
    }

    private function addToAssetSmallTool($mainAsset, $childAssets)
    {
        $groupedAssets = [];
        $totalDepreciationBasis = 0;
        $totalAcquisitionCost = 0;

        foreach ($childAssets as $asset) {
            $childAsset = FixedAsset::find($asset);

            $key = $childAsset->asset_description . '-' . $childAsset->warehouse_id . '-' . $childAsset->asset_specification . '-' . $childAsset->reference_number;

            if (!isset($groupedAssets[$key])) {
                $groupedAssets[$key] = [
                    'fixed_asset_id' => $mainAsset,
                    'reference_number' => $childAsset->reference_number,
                    'description' => $childAsset->asset_description,
                    'specification' => $childAsset->asset_specification,
                    'receiver' => $childAsset->receiver,
                    'acquisition_cost' => $childAsset->acquisition_cost,
                    'pr_number' => $childAsset->ymir_pr_number,
                    'po_number' => $childAsset->po_number,
                    'rr_number' => $childAsset->rr_number,
                    'status_description' => 'Good',
                    'quantity' => 0,
                ];
            }

            $groupedAssets[$key]['quantity'] += $childAsset->quantity;
            $totalDepreciationBasis += $childAsset->formula->depreciable_basis;
            $totalAcquisitionCost += $childAsset->formula->acquisition_cost;
        }

        foreach ($groupedAssets as $assetData) {
            AssetSmallTool::create($assetData);
            AssetRequest::where('reference_number', $assetData['reference_number'])
                ->update(['is_asset_small_tool' => 1]);
        }

        // Update the main asset with the total depreciation basis
        $mainAssetRecord = FixedAsset::find($mainAsset);
        $mainAssetRecord->formula->depreciable_basis += $totalDepreciationBasis;
        $mainAssetRecord->acquisition_cost += $totalAcquisitionCost;
        $mainAssetRecord->formula->acquisition_cost += $totalAcquisitionCost;
        $mainAssetRecord->save();
        $mainAssetRecord->formula->save();
    }

    public function updateChildAsset(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $childAsset = AssetSmallTool::find($id);
            if (!$childAsset) {
                return $this->responseNotFound('Asset not found');
            }
            $childAsset->update([
//                'description' => $request->description,
                'quantity' => $request->quantity,
            ]);
//            $childAsset->formula->update($request->all());

            DB::commit();
            return $this->responseSuccess('Successfully updated');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->responseUnprocessable('Something went wrong');
        }
    }

    public function removeChildAsset($id)
    {
        DB::beginTransaction();
        try {
            $fixedAsset = AssetSmallTool::find($id);
            if (!$fixedAsset) {
                return $this->responseNotFound('Asset not found');
            }
            $fixedAsset->update([
                'is_active' => false,
            ]);
            $fixedAsset->delete();
            DB::commit();
            return $this->responseSuccess('Successfully remove');
        } catch (\Exception $e) {
            DB::rollBack();
//            return $e->getMessage();
            return $this->responseUnprocessable('Something went wrong');
        }
    }

    public function setNotPrintableSmallTools(Request $request)
    {
        DB::beginTransaction();
        try {
            $smallTools = $request->get('fixed_asset_id', []);
            $isPrintable = $request->get('is_printable', false);

            $fixedAsset = FixedAsset::whereIn('id', $smallTools)
                ->wheredoesnthave('assetSmallTools')
//                ->whereHas('typeOfRequest', function ($query) {
//                    $query->whereIn('type_of_request_name', ['Small Tools', 'Small Tool']);
//                })
                ->get();
            if ($fixedAsset->isEmpty()) {
                return $this->responseNotFound('No Data Found');
            }

            foreach ($smallTools as $assetId) {
                $transactionNumber = FixedAsset::find($assetId)->transaction_number;
                $referenceNumber = FixedAsset::find($assetId)->reference_number;

                // Get all assets with same transaction number except current asset
                $relatedAssets = FixedAsset::where('transaction_number', $transactionNumber)
                    ->where('id', '!=', $assetId)
                    ->get();

                // Check if all related assets have can_release = 1
                $allReleasable = $relatedAssets->every(function ($asset) {
                    return $asset->can_release === 1;
                });

                if ($allReleasable) {
                    // Update AssetRequest filter column
                    \App\Models\AssetRequest::where('transaction_number', $transactionNumber)
                        ->whereNotIn('filter', ['Claimed'])
                        ->update(['filter' => 'Ready to Pickup']);
                }else{
                    // Update AssetRequest filter column
                    \App\Models\AssetRequest::where('reference_number', $referenceNumber)
                        ->whereNotIn('filter', ['Claimed'])
                        ->update(['filter' => 'Ready to Pickup']);
                }
            }

            $fixedAsset->each(function ($asset) use ($isPrintable) {
                $asset->update([
                    'is_printable' => $isPrintable,
                    'can_release' => $isPrintable == 1 ? 0 : 1,
                ]);
            });

            DB::commit();
            return $this->responseSuccess('Successfully set as not printable');
        } catch (\Exception $e) {
            DB::rollBack();
//            return $e->getMessage();
            return $this->responseUnprocessable('Something went wrong');
        }
    }


    public function unGroupAsset(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $asset = FixedAsset::where('id', $id)
                ->where('is_released', 0)
                ->first();
            if (!$asset) {
                return $this->responseNotFound('Asset not found');
            }

            $childAssets = AssetSmallTool::where('fixed_asset_id', $id)->get();
            if ($childAssets->isEmpty()) {
                return $this->responseNotFound('No child asset found');
            }

            $childAssets->each(function ($childAsset) {
                $childAsset->forcedelete();
            });

//            $transactionNumber = $childAssets->pluck('reference_number');

            $assetRequest = AssetRequest::whereIn('reference_number', $childAssets->pluck('reference_number')->toArray())
                ->where('is_asset_small_tool', 1)
                ->get();

//            return $this->responseUnprocessable($assetRequest);

            $assetRequest->each(function ($request) {
                $request->update(['is_asset_small_tool' => 0, 'filter' => 'Asset Tagging']);
            });

            $referenceNumbers = $childAssets->mapWithKeys(function ($item) {
                return [$item->reference_number => $item->quantity];
            })->toArray();
            $totalAcquisitionCost = 0;

            foreach ($referenceNumbers as $referenceNumber => $quantity) {
                for ($i = 0; $i < $quantity; $i++) {
                    $fixedAsset = FixedAsset::onlyTrashed()->where('reference_number', $referenceNumber)->first();
                    if ($fixedAsset) {
                        $fixedAsset->restore();
                        $totalAcquisitionCost += $fixedAsset->acquisition_cost;
                    }
                    $fixedAsset->update(['can_release' => 0]);
                }
            }
            $actualAcquisitionCost = $asset->acquisition_cost - $totalAcquisitionCost;

            $asset->update([
                'acquisition_cost' => $actualAcquisitionCost,
            ]);
            $asset->formula->update([
                'acquisition_cost' => $actualAcquisitionCost,
            ]);

            DB::commit();
            return $this->responseSuccess('Successfully ungrouped');
        } catch (\Exception $e) {
            DB::rollBack();
            return $e->getMessage() . '-' . $e->getLine();
            return $this->responseUnprocessable('Something went wrong');
        }
    }
}

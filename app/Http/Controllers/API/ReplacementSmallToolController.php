<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AssetRequest;
use App\Models\AssetSmallTool;
use App\Traits\SmallToolReplacementHandler;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReplacementSmallToolController extends Controller
{
    use ApiResponse, SmallToolReplacementHandler;

    public function index(Request $request)
    {
        $userWarehouseId = auth('sanctum')->user()->warehouse_id;
        $released = $request->input('isReleased', 0);
        $assetSmallTools = AssetSmallTool::with('fixedAsset', 'item')
            ->where('is_released', $released)
//            ->whereNotNull('rr_number')
            ->where('to_release', "!=", $released)
            ->where('receiving_warehouse_id', $userWarehouseId)
//            ->whereHas('receivingWarehouse', function ($query) use ($userWarehouseId) {
//                $query->where('sync_id', $userWarehouseId);
//            })
            ->useFilters()
            ->dynamicPaginate();
        return $this->replacementSTDataViewing($assetSmallTools);
    }

    public function releaseSmallToolReplacement(Request $request)
    {
        DB::beginTransaction();
        try {
            $images = [
                'receiverImg' => $request->get('receiver_img') ?? null,
//            'assignmentMemoImg' => $request->get('assignment_memo_img') ?? null,
                'authorizationMemoImg' => $request->get('authorization_memo_img') ?? null
            ];
            $receivedBy = $request->get('received_by');
            $itemId = $request->input('small_tools_id', []); //item id
            $assetSmallTools = AssetSmallTool::whereIn('id', $itemId)->get();
            if ($assetSmallTools->isEmpty()) {
                return $this->responseUnprocessable('Data not found');
            }

            foreach ($assetSmallTools as $assetSmallTool) {
                $updateResult = $assetSmallTool->update(['to_release' => 0, 'is_released' => 1, 'receiver' => $receivedBy]);
                AssetRequest::where('reference_number', $assetSmallTool->reference_number)
                    ->update([
                        'filter' => 'Claimed',
                        'is_claimed' => 1,
                    ]);

                $updateToReplaced = AssetSmallTool::where('fixed_asset_id', $assetSmallTool->fixed_asset_id)
                    ->whereHas('item', function ($query) use ($assetSmallTool) {
                        $query->where('item_code', $assetSmallTool->item->item_code);
                    })
                    ->where('status_description', 'Good')
                    ->where(function ($query) {
                        $query->where('is_released', 1)
                            ->orWhere(function ($query) {
                                $query->where('is_released', 0)
                                    ->where('to_release', 0);
                            });
                    })->first();
                if ($updateToReplaced) {
                    $updateToReplaced->update(['status_description' => 'Replaced', 'is_active' => 0]);
                }


                if ($updateResult) {
                    $assetSmallTool->storeBase64Images($images);
                    $this->stItemReleaseActivityLog($assetSmallTool);
                }
            }
            DB::commit();
            return $this->responseSuccess('Released Successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->responseUnprocessable('something went wrong');
        }

    }

    public function stItemReleaseActivityLog($AssetToRelease)
    {
        $user = auth('sanctum')->user();
        $assetRequests = new AssetRequest();
        activity()
            ->causedBy($user)
            ->performedOn($assetRequests)
            ->withProperties($this->composeLogPropertiesRelease($AssetToRelease))
            ->inLog("Released")
            ->tap(function ($activity) use ($AssetToRelease) {
                $activity->subject_id = $AssetToRelease->transaction_number;
            })
            ->log('Released');
    }

    public function composeLogPropertiesRelease($AssetToRelease)
    {
        $user = auth('sanctum')->user();
        return [
            'transaction_number' => $AssetToRelease->transaction_number,
            "received_by" => $AssetToRelease->receiver,
            'item_id' => $AssetToRelease->item_id,
            'fixed_asset_id' => $AssetToRelease->fixed_asset_id,
            'vladimir_tag_number' => $AssetToRelease->fixedAsset->vladimir_tag_number,
            'asset_description' => $AssetToRelease->fixedAsset->asset_description,
            'pr_number' => $AssetToRelease->pr_number,
            'po_number' => $AssetToRelease->po_number,
            'rr_number' => $AssetToRelease->rr_number,
            'quantity' => $AssetToRelease->quantity,
        ];
    }

    public function smallToolItemUpdate(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $quantity = $request->input('quantity');
            $status = $request->input('status_description');
            $description = $request->input('description');
            $currStatus = $request->input('curr_status');
            $fixedAssetId = $request->input('fixed_asset_id');
            $isActive = $status === 'Good' ? 1 : 0;

            if ($status == $currStatus) {
                return $this->responseSuccess('No Changes Made');
            }

            for ($i = 0; $i < $quantity; $i++) {
                $updateResult = AssetSmallTool::where('small_tool_id', $id)
                    ->where('status_description', $currStatus)
                    ->where('fixed_asset_id', $fixedAssetId)
                    ->where(function ($query) {
                        $query->where('is_released', 1)
                            ->orWhere(function ($query) {
                                $query->where('is_released', 0)
                                    ->where('to_release', 0);
                            });
                    })
                    ->first();
                if (!$updateResult) {
                    return $this->responseNotFound('Data not found');
                }
                $updateResult->update(['status_description' => $status, 'is_active' => $isActive]);
            }
            DB::commit();
            return $this->responseSuccess('Updated Successfully');
        } catch (\Exception $e) {
            DB::rollBack();
//            return $e->getMessage();
            return $this->responseUnprocessable('something went wrong');
        }


    }
}

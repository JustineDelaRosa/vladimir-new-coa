<?php

namespace App\Http\Controllers\API;

use App\Models\AssetRequest;
use Illuminate\Http\Request;
use App\Traits\AddingPoHandler;
use Illuminate\Support\Facades\DB;
use App\Traits\AssetRequestHandler;
use App\Http\Controllers\Controller;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Http\Requests\AddingPO\UpdateAddingPoRequest;

class AddingPoController extends Controller
{
    use ApiResponse, AssetRequestHandler, AddingPoHandler;

    public function index(Request $request)
    {
        $toPo = $request->get('toPo', null);
        $perPage = $request->input('per_page', null);

        $assetRequest = $this->createAssetRequestQuery($toPo)->get()
            ->groupBy('transaction_number')->map(function ($assetRequestCollection) {
                $assetRequest = $assetRequestCollection->first();
                $assetRequest->quantity = $assetRequestCollection->sum('quantity');
                return $this->transformIndexAssetRequest($assetRequest);
            })->values();

        if ($perPage !== null) {
            $assetRequest = $this->paginate($request, $assetRequest, $perPage);
        }

        return $assetRequest;
    }

    public function store(Request $request)
    {
    }

    public function show($id)
    {
    }

    public function update(UpdateAddingPoRequest $request, $id)
    {
        $assetRequest = AssetRequest::find($id);
        if ($assetRequest == null) {
            return $this->responseNotFound('Asset Request not found!');
        }

        $this->updatePoAssetRequest($assetRequest, $request);
        $this->activityLogPo($assetRequest, $request->po_number, $request->rr_number, 0, false, false);

        if ($assetRequest->quantity === $assetRequest->quantity_delivered) {
            $this->createNewAssetRequests($assetRequest);
        }

        return $this->responseSuccess('PO number and RR number added successfully!');
    }

    public function destroy($id)
    {
        $assetRequest = AssetRequest::where('id', $id)->first();
        if (!$assetRequest) {
            return $this->responseNotFound('Asset Request not found!');
        }
        if ($assetRequest->quantity_delivered == null || $assetRequest->quantity_delivered == 0) {
            $this->deleteAssetRequestPo($assetRequest);
            return $this->responseSuccess('Item removed successfully!');
        }
        if ($assetRequest->quantity !== $assetRequest->quantity_delivered) {
            // $this->activityLogPo($assetRequest, $assetRequest->po_number, $assetRequest->rr_number, true);
            $removedQuantity = $this->quantityRemovedHolder($assetRequest);
            $storedRemovedQuantity = $removedQuantity;
            $assetRequest->quantity = $assetRequest->quantity_delivered;
            $assetRequest->save();
            $this->activityLogPo($assetRequest, $assetRequest->po_number, $assetRequest->rr_number, $storedRemovedQuantity, true, false);
            $this->createNewAssetRequests($assetRequest);
            // $this->activityLogPo($assetRequest, $request->po_number, $request->rr_number, $remove = true);
            return $this->responseSuccess('Remaining quantity removed successfully!');
        }
    }
}

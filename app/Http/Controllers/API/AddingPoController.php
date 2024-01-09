<?php

namespace App\Http\Controllers\API;

use App\Models\AssetRequest;
use Illuminate\Http\Request;
use App\Traits\AssetRequestHandler;
use App\Http\Controllers\Controller;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Http\Requests\AddingPO\UpdateAddingPoRequest;
use App\Traits\AddingPoHandler;

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
        $poNumber = $request->po_number;
        $rrNumber = $request->rr_number;
        $supplierId = $request->supplier_id;
        $deliveryDate = $request->delivery_date;
        $quantityDelivered = $request->quantity_delivered;
        $unitPrice = $request->unit_price;

        $assetRequest = AssetRequest::where('id', $id)->first();
        if ($assetRequest == null) {
            return $this->responseNotFound('Asset Request not found!');
        }

        $assetRequest->update([
            'po_number' => $poNumber,
            'rr_number' => $rrNumber,
            'supplier_id' => $supplierId,
            'delivery_date' => $deliveryDate,
            'quantity_delivered' => $assetRequest->quantity_delivered + $quantityDelivered,
            'unit_price' => $unitPrice,
        ]);
        //check if the quantity and quantity delivered is equal after updating
        if ($assetRequest->quantity === $assetRequest->quantity_delivered) {
            foreach (range(1, $assetRequest->quantity) as $index) {
                $newAssetRequest = $assetRequest->replicate();
                $newAssetRequest->quantity = 1;
                $newAssetRequest->quantity_delivered = 1;
                $newAssetRequest->save();
            }
            $assetRequest->update([
                'quantity' => 1,
                'quantity_delivered' => 1,
            ]);
        }

        return $this->responseSuccess(
            'PO number and RR number added successfully!',
            $assetRequest
        );
    }

    public function destroy($id)
    {
        $assetRequest = AssetRequest::where('id', $id)->first();
        if (!$assetRequest) {
            return $this->responseNotFound('Asset Request not found!');
        }

        if ($assetRequest->quantity !== $assetRequest->quantity_delivered) {
            $assetRequest->quantity = $assetRequest->quantity_delivered;
            $assetRequest->save();
            return $this->responseSuccess('Successfully removed!');
        }

        $assetRequest->delete();
        return $this->responseSuccess('Successfully removed!');
    }
}

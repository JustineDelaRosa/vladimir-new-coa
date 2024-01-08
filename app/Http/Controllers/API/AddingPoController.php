<?php

namespace App\Http\Controllers\API;

use App\Models\AssetRequest;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Essa\APIToolKit\Api\ApiResponse;
use App\Http\Requests\AddingPO\UpdateAddingPoRequest;

class AddingPoController extends Controller
{
    use ApiResponse;

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $toPo = $request->get('toPo', null);
        return 'asfasfsafd';
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
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
            'quantity' => $quantityDelivered,
            'quantity_delivered' => $quantityDelivered,
            'unit_price' => $unitPrice,
        ]);

        return $this->responseSuccess(
            'PO number and RR number added successfully!',
            $assetRequest
        );
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $assetRequest = AssetRequest::where('id', $id)->first();

        // Check if the asset request exists
        if (!$assetRequest) {
            return $this->responseNotFound('Asset Request not found!');
        }

        $totalDelivered = $assetRequest->quantity - $assetRequest->quantity_delivered;

        // If no quantity has been delivered, delete the asset request
        if ($assetRequest->quantity_delivered <= 0) {
            $assetRequest->delete();
            return $this->responseSuccess('Successfully deleted!');
        }

        // If some quantity has been delivered, update the quantity and save
        $assetRequest->quantity = $totalDelivered;
        $assetRequest->save();

        return $this->responseSuccess('Successfully updated!');
    }
}

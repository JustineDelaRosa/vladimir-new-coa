<?php

namespace App\Http\Controllers\API;

use App\Models\Approvers;
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

    public function show(Request $request, $transactionNumber)
    {
        $perPage = $request->input('per_page', null);
        $requiredRole = ['Purchase Order', 'Admin', 'Super Admin', 'Warehouse', 'Purchase Request'];
        $checkUserRole = auth('sanctum')->user()->role->role_name;
        if (in_array($checkUserRole, $requiredRole)) {
            $assetRequest = AssetRequest::where('transaction_number', $transactionNumber)
                ->orderByRaw('(quantity > quantity_delivered) desc')
                ->get();
        } else {
            return $this->responseUnprocessable('You are not allowed to view this transaction.');
        }
        $assetRequest = $this->transformShowAssetRequest($assetRequest);

        if ($perPage !== null) {
            $page = $request->input('page', 1);
            $offset = $page * $perPage - $perPage;
            $assetRequest = new LengthAwarePaginator($assetRequest->slice($offset, $perPage)->values(), $assetRequest->count(), $perPage, $page, [
                'path' => $request->url(),
                'query' => $request->query(),
            ]);
        }

        if ($assetRequest->isEmpty()) {
            return $this->responseUnprocessable('Asset Request not found.');
        }

        return $assetRequest;
    }

    public function update(UpdateAddingPoRequest $request, $id)
    {
        $assetRequest = AssetRequest::find($id);
        if ($assetRequest == null) {
            return $this->responseNotFound('Asset Request not found!');
        }

        $this->updatePoAssetRequest($assetRequest, $request);
        $this->activityLogPo($assetRequest, $request->po_number, $request->rr_number, 0, false, false);
        $this->getAllitems($assetRequest->transaction_number);

        // $remaining = $this->calculateRemainingQuantity($assetRequest->transaction_number, $forTimeline = false);
        // if ($remaining == 0) {
        //     // $this->createNewAssetRequests($assetRequest);
        //     $this->getAllitems($assetRequest->transaction_number);
        // }

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
        return $this->responseUnprocessable('Item cannot be removed!');
        // if ($assetRequest->quantity !== $assetRequest->quantity_delivered) {
        //     return $this->handleQuantityMismatch($assetRequest);
        // }
    }
}

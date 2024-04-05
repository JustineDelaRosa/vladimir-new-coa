<?php

namespace App\Http\Controllers\API;

use App\Models\Approvers;
use App\Models\AssetRequest;
use App\Traits\RequestShowDataHandler;
use Illuminate\Http\Request;
use App\Traits\AddingPoHandler;
use Illuminate\Support\Facades\DB;
use App\Traits\AssetRequestHandler;
use App\Http\Controllers\Controller;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Http\Requests\AddingPo\UpdateAddingPoRequest;

class AddingPoController extends Controller
{
    use ApiResponse, AssetRequestHandler, AddingPoHandler,RequestShowDataHandler;

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
        $requiredRole =array_map('strtolower',['Purchase Order', 'Admin', 'Super Admin', 'Warehouse', 'Purchase Request','Po-Receiving']);
        $checkUserRole = strtolower(auth('sanctum')->user()->role->role_name);

        if (in_array($checkUserRole, $requiredRole)) {
            $nonSoftDeletedTransactionNumbers = AssetRequest::whereNull('deleted_at')->pluck('reference_number');
            $assetRequest = AssetRequest::withTrashed()->where('transaction_number', $transactionNumber);
        } else {
            return $this->responseUnprocessable('You are not allowed to view this transaction.');
        }

        $assetRequest->where(function ($query) use ($nonSoftDeletedTransactionNumbers) {
            $query->whereNull('deleted_at')
                ->orWhere(function ($query) use ($nonSoftDeletedTransactionNumbers) {
                    $query->whereNotNull('deleted_at')
                        ->whereNotIn('reference_number', $nonSoftDeletedTransactionNumbers);
                });
        });

        $assetRequest->orderByRaw('(quantity > quantity_delivered) desc');

        $assetRequest = $this->responseData($assetRequest->dynamicPaginate());

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
//        return $request->all();

        $this->updatePoAssetRequest($assetRequest, $request);
        $this->activityLogPo($assetRequest, $request->po_number, $request->rr_number, 0, false, false);
        $this->getAllitems($id, $request->quantity_delivered);
        $this->updateRequestStatusFilter($assetRequest);

        return $this->responseSuccess('PO number and RR number added successfully!');
    }

    public function destroy($id, Request $request)
    {
        $transactionNumber = $request->get('transaction_number', null);
        $remarks = $request->get('remarks', null);

        if ($transactionNumber) {
            return $this->handleTransactionNumberCase($transactionNumber);
        } else {
            return $this->handleIdCase($id, $remarks);
        }
    }
}

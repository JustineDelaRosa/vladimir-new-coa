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
use Illuminate\Support\Facades\Http;

class AddingPoController extends Controller
{
    use ApiResponse, AssetRequestHandler, AddingPoHandler, RequestShowDataHandler;

    public function index(Request $request)
    {
        $toPo = $request->get('toPo', null);
        $perPage = $request->input('per_page', null);

        $assetRequest = $this->createAssetRequestQuery($toPo)->get()
            ->groupBy('transaction_number')->map(function ($assetRequestCollection) {
                $assetRequest = $assetRequestCollection->first();
                $assetRequest->quantity = $assetRequestCollection->sum('quantity');
                $anyRecentlyUpdated = $assetRequestCollection->contains(function ($item) {
                    return $item->updated_at->diffInMinutes(now()) < 2;
                });

                $assetRequest->newly_sync = $anyRecentlyUpdated ? 1 : 0;
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
        $requiredRole = array_map('strtolower', ['Purchase Order', 'Admin', 'Super Admin', 'Warehouse', 'Purchase Request', 'Po-Receiving']);
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

    public function handleSyncData(Request $request)
    {
        $userLocationId = auth('sanctum')->user()->location_id;
        $data = $request->get('result');

        $apiUrl = config('ymir-api.ymir_put_api_url');
        $bearerToken = config('ymir-api.ymir_put_api_token');
        if ($data == null) {
            return $this->responseUnprocessable('No data to sync');
        }
        if (is_null($apiUrl) || is_null($bearerToken)) {
            return $this->responseUnprocessable('API URL or Bearer Token is not configured properly.');
        }
        $itemReceivedCount = 0;
        $cancelledCount = 0;
        $rrNumbers = [];
        foreach ($data as $asset) {
            $transactionNumber = $asset['transaction_no'];
            $poNo = $asset['po_number'];
            $prNo = $asset['pr_number'];
            $deletedAt = $asset['cancelled_at'];

            $assetRequest = AssetRequest::where('filter', 'Sent to Ymir')
                ->where('transaction_number', $transactionNumber)
                ->whereHas('receivingWarehouse', function ($query) use ($userLocationId) {
                    $query->where('location_id', $userLocationId);
                })->get();
            if($assetRequest->isEmpty()){
                return $this->responseUnprocessable('No asset request to sync.');
            }
            if ($assetRequest) {
                foreach ($asset['order'] as $order) {
                    $supplier = $order['supplier'];
                    $unitPrice = $order['unit_price'];
                    $referenceNumber = $order['item_code'];
                    $remaining = $order['remaining'];


                    if ($deletedAt != null) {
                        foreach ($assetRequest as $request) {
                            $replicatedAssetRequest = $assetRequest->replicate();
                            $replicatedAssetRequest->quantity = $remaining;
                            $replicatedAssetRequest->quantity_delivered = 0;
                            $replicatedAssetRequest->filter = NULL;
                            $replicatedAssetRequest->save();
                            $replicatedAssetRequest->delete();

                            $request->quantity -= $remaining;
                            $request->save();

                            $cancelledCount++;
                        }
                    }
                    $itemRequest = $assetRequest->where('reference_number', $referenceNumber)->first();
                    foreach ($order['rr_orders'] as $rr) {
                        $deliveryDate = $rr['delivery_date'];
                        $itemRemaining = $rr['remaining'];
                        $rrNumber = $rr['rr_number'];

                        if ($rr['sync'] == 1) {
                            continue;
                        }

                        $itemRequest->update([
                            'synced' => 1,
                            'pr_number' => $prNo,
                            'po_number' => $poNo,
                            'rr_number' => $rrNumber,
                            'supplier_id' => $supplier,
                            'quantity_delivered' => $itemRequest->quantity_delivered + $rr['quantity_receive'],
                            'acquisition_date' => $deliveryDate,
                            'acquisition_cost' => $unitPrice,
                        ]);
                        $this->createNewAssetRequests($itemRequest, $rr['quantity_receive']);
                        $rrNumbers[] = [
                            'rr_number' => $rr['rr_number'],
                        ];
                        $itemReceivedCount++;
                    }
                    $this->updateRequestStatusFilter($itemRequest);
                }
            }
        }
        if (!empty($rrNumbers)) {
            Http::withHeaders(['Authorization' => 'Bearer ' . $bearerToken])
                ->put($apiUrl, ['rr_numbers' => $rrNumbers]);
        }

        $data = [
            'synced' => $itemReceivedCount,
            'cancelled' => $cancelledCount,
        ];
        return $this->responseSuccess('successfully sync received asset', $data);
    }
//    public function handleSyncData(Request $request)
//    {
//        $userLocationId = auth('sanctum')->user()->location_id;
//        $data = $request->get('result');
//        $apiUrl = config('api.ymir_put_api_url');
//        $bearerToken = config('api.ymir_put_api_token');
//
//        $validationResponse = $this->validateSyncData($data, $apiUrl, $bearerToken);
//        if ($validationResponse !== null) {
//            return $validationResponse;
//        }
//
//        foreach ($data as $asset) {
//            $this->processAsset($asset, $userLocationId, $bearerToken, $apiUrl);
//        }
//
//        return $this->responseSuccess('Successfully synced received asset');
//    }
}

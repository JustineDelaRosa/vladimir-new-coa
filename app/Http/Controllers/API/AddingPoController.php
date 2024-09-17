<?php

namespace App\Http\Controllers\API;

use App\Models\Approvers;
use App\Models\AssetRequest;
use App\Models\FixedAsset;
use App\Models\YmirPRTransaction;
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
        $from = $request->get('from', null);
        $to = $request->get('to', null);


        $assetRequest = $this->createAssetRequestQuery($toPo, $from, $to)->get()
            ->groupBy('transaction_number')->map(function ($assetRequestCollection) {
                $assetRequest = $assetRequestCollection->first();
                $assetRequest->quantity = $assetRequestCollection->sum('quantity');
                $assetRequest->quantity_delivered = $assetRequestCollection->sum('quantity_delivered');
                //add all the quantity of soft deleted asset request
                $cancelled = AssetRequest::onlyTrashed()->where('transaction_number', $assetRequest->transaction_number)->sum('quantity');
                $anyRecentlyUpdated = $assetRequestCollection->contains(function ($item) {
                    return $item->updated_at->diffInMinutes(now()) < 2;
                });
                $poNumber = $assetRequestCollection->pluck('po_number')->filter()->unique()->implode(',');
                $rrNumber = $assetRequestCollection->pluck('rr_number')->filter()->unique()->implode(',');
                $rrNumber = collect(explode(',', $rrNumber))->unique()->implode(',');


                $assetRequest->po_number = $poNumber;
                $assetRequest->rr_number = $rrNumber;
                $assetRequest->cancelled = $cancelled;
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
        $poData = [];
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
//            return $assetRequest;
            if ($assetRequest->isEmpty()) {
                continue;
//                return $this->responseUnprocessable('No asset request found for transaction number ' . $transactionNumber);
            }
            if ($assetRequest) {
                foreach ($asset['order'] as $order) {
                    $supplier = $order['supplier'];
                    $unitPrice = $order['unit_price'];
                    $referenceNumber = $order['item_code'];
                    $remaining = $order['remaining'];
                    $inclusion = $order['remarks'];

                    if ($deletedAt != null) {
                        foreach ($assetRequest as $request) {
                            $replicatedAssetRequest = $request->replicate();
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
                    $itemRequest = AssetRequest::where('transaction_number', $transactionNumber)
                        ->where('reference_number', $referenceNumber)
                        ->where('pr_number', $prNo)
                        ->first();

                    $rrNumberArray = [];
                    foreach ($order['rr_orders'] as $rr) {

                        $deliveryDate = $rr['delivery_date'];
                        $itemRemaining = $rr['remaining'];
                        $rrNumber = $rr['rr_number'];

                        // store the po_number in the array transaction number and reference number
                        $poData[] = [
                            'transaction_number' => $transactionNumber,
                            'reference_number' => $referenceNumber,
                            'po_number' => $poNo,
                        ];

                        if (!in_array($rrNumber, $rrNumberArray)) {
                            $rrNumberArray[] = $rrNumber;
                        }
                        $rrNumberString = implode(',', $rrNumberArray);

                        if ($rr['sync'] == 1) {
                            continue;
                        }

                        $itemRequest->update([
                            'synced' => 1,
//                            'pr_number' => $prNo,
                            'po_number' => $poNo,
                            'rr_number' => $rrNumberString,
                            'supplier_id' => $supplier,
                            'quantity_delivered' => $itemRequest->quantity_delivered + $rr['quantity_receive'],
                            'acquisition_date' => $deliveryDate,
                            'acquisition_cost' => $unitPrice,
                            'received_at' => now(),
                        ]);
                        $this->createNewAssetRequests($itemRequest, $rr['quantity_receive'], $inclusion);
                        $rrNumbers[] = $rr['rr_number'];
                        $itemReceivedCount++;
                    }
                    $rrNumberArray = [];
                    $this->updateRequestStatusFilter($itemRequest);
                }
            }
        }

//        $this->storePOs($poData);
        if (!empty($rrNumbers)) {
            Http::withHeaders(['Authorization' => 'Bearer ' . $bearerToken])
                ->put($apiUrl, ['rr_number' => $rrNumbers]);
        }

        $data = [
            'synced' => $itemReceivedCount,
            'cancelled' => $cancelledCount,
        ];
        return $this->responseSuccess('successfully sync received asset', $data);
    }

    //TODO: Probably not necessary anymore
//    public function storePOs(array $data)
//    {
//        //map the $data and organize the unique po_number with the same transaction number and reference number
//        $poData = collect($data)->groupBy('po_number')->map(function ($poData) {
//            return $poData->groupBy('transaction_number')->map(function ($transactionData) {
//                return $transactionData->groupBy('reference_number')->map(function ($referenceData) {
//                    return $referenceData->pluck('po_number')->first();
//                });
//            });
//        });
//
//        //store the po_number in the asset request
//        foreach ($poData as $poNumber) {
//            foreach ($poNumber as $transactionNumber) {
//                foreach ($transactionNumber as $referenceNumber => $poNo) {
//                    $assetRequest = AssetRequest::where('transaction_number', $transactionNumber)
//                        ->where('reference_number', $referenceNumber)
//                        ->update(['po_number' => $poNo]);
//                }
//            }
//        }
//
//        return $this->responseSuccess('PO number stored successfully');
//    }


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

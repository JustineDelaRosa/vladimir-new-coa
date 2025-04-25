<?php

namespace App\Http\Controllers\API;

use App\Models\Approvers;
use App\Models\AssetRequest;
use App\Models\AssetSmallTool;
use App\Models\FixedAsset;
use App\Models\Item;
use App\Models\SmallTools;
use App\Models\Supplier;
use App\Models\YmirPRTransaction;
use App\Traits\AssetReleaseHandler;
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
        //validate the request
        $this->validate($request, [
            'toPo' => 'nullable|boolean',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'per_page' => 'nullable|integer',
        ]);

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
        $explicitRoles = ['Purchase Order', 'Admin', 'Super Admin', 'Warehouse', 'Purchase Request', 'Po-Receiving'];
        $checkUserRole = strtolower(auth('sanctum')->user()->role->role_name);

    // Check if user has an explicit role or any role containing "warehouse"
        $hasRequiredRole = in_array($checkUserRole, array_map('strtolower', $explicitRoles), true) ||
            str_contains($checkUserRole, 'warehouse');

        if ($hasRequiredRole) {
            $nonSoftDeletedTransactionNumbers = AssetRequest::whereNull('deleted_at')->pluck('reference_number');
//            $assetRequest = AssetRequest::withTrashed()->where('transaction_number', $transactionNumber);
            $assetRequest = AssetRequest::where('transaction_number', $transactionNumber);
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
        DB::beginTransaction();
        try {


            $apiUrl = config('ymir-api.ymir_put_api_url');
            $bearerToken = config('ymir-api.ymir_put_api_token');
            if ($data == null) {
                return $this->responseUnprocessable('No data to sync');
            }
            if (is_null($apiUrl) || is_null($bearerToken)) {
                return $this->responseUnprocessable('API URL or Bearer Token is not configured properly.');
            }

//        return $this->receivingLog($data);

            $itemReceivedCount = 0;
            $cancelledCount = 0;
            $poData = [];
            $rrNumbers = [];
            $rrNumberIdArray = [];
            $totalQuantity = 0;
            $totalQuantityDelivered = 0;
//            foreach ($data as $asset) {

//            $poNo = $asset['po_number'];
            $deletedAt = $data['cancelled_at'];
            $causer = $data['causer'] ?? null;
            $rrNumber = $data['rr_year_number_id'];
            foreach ($data['orders'] as $order) {
                $supplier = $order['supplier'];
                $unitPrice = $order['unit_price'];
                $transactionNumber = $order['transaction_no'];
                $referenceNumber = $order['reference_no'];
                $remaining = $order['remaining'];
                $itemName = $order['item_name'];
                $quantityDelivered = $order['quantity_delivered'];

//                $itemRequest = AssetRequest::where('reference_number', $referenceNumber)->first();
//                $isPeso = $itemRequest->uom->uom_name === 'PESO';

                $assetRequest = AssetRequest::whereIn('filter', ['Sent to Ymir', 'Partially Received', 'Po Created', 'Asset Tagging'])
                    ->where('transaction_number', $transactionNumber)
//                    ->where('asset_description', $itemName)
//                    ->whereHas('receivingWarehouse', function ($query) use ($userLocationId) {
//                        $query->where('location_id', $userLocationId);
//                    })
                    ->get();
                $totalQuantity = $assetRequest->sum('quantity');

                if ($assetRequest->isEmpty()) {
                    continue;
                }
                if ($assetRequest) {
                    /*if ($deletedAt != null) {
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
                    }*/
                    $itemRequest = AssetRequest::where('transaction_number', $transactionNumber)
                        ->where('reference_number', $referenceNumber)
                        ->first();

                    $rrNumberArray = [];
                    $poNumberArray = [];

//                    foreach ($order['rr_orders'] as $rr) {
//                    $inclusion = $order['rr_orders']['remarks'];
                    $deliveryDate = $order['rr_orders']['delivery_date'];
                    $itemRemaining = $order['rr_orders']['remaining'];
                    $rrNumberId = $order['rr_orders']['rr_number'];
//                    $poNumber = $order['rr_orders']['po_id'];
                    $prYearNumber = $order['rr_orders']['pr_year_number_id'];
//                    $rrNumber = $order['rr_orders']['pr_year_number_id'];
                    $poNumber = $order['rr_orders']['po_year_number_id'];
                    $initialCreditId = $order['rr_orders']['initial_credit_id'];

                    // store the po_number in the array transaction number and reference number
                    /*                    $poData[] = [
                                            'transaction_number' => $transactionNumber,
                                            'reference_number' => $referenceNumber,
                                            'po_number' => $poNumber,
                                        ];*/


                    //TODO: Do this also to PO Number(a request can have multiple PO Numbers)
                    if (!in_array($rrNumber, $rrNumberArray)) {
                        $rrNumberArray[] = $rrNumber;
                    }
                    $rrNumberString = implode(',', $rrNumberArray);

                    if (!in_array($rrNumberId, $rrNumberIdArray)) {
                        $rrNumberIdArray[] = $rrNumberId;
                    }
                    $rrNumberIdString = implode(',', $rrNumberIdArray);

                    if (!in_array($poNumber, $poNumberArray)) {
                        $poNumberArray[] = $poNumber;
                    }
                    $poNumberString = implode(',', $poNumberArray);


                    if ($order['rr_orders']['sync'] == 1) {
                        continue;
                    }
                    $totalQuantityDelivered = $itemRequest->quantity_delivered + $order['rr_orders']['quantity_received'];
                    $supplierCheck = Supplier::where('sync_id', $supplier)->first();
                    if ($supplierCheck == null) {
                        $this->responseUnprocessable('Supplier not found, Please sync vladimir Supplier first');
                    }
                    $itemRequest->update([
                        'synced' => 1,
                        //'pr_number' => $prNo,
                        'po_number' => $poNumberString, //$poNumber,
                        'rr_number' => $rrNumberString,
                        'rr_id' => $rrNumberIdString,
                        'ymir_pr_number' => $prYearNumber,
                        'supplier_id' => $supplier,
                        'quantity' => $itemRequest->quantity, //$isPeso ? 1 :
                        'quantity_delivered' => $itemRequest->quantity_delivered + $order['rr_orders']['quantity_received'], //$isPeso ? 1 :
//                        'filter' => $itemRequest->quantity_delivered + $order['rr_orders']['quantity_received'] == $itemRequest->quantity ? 'Received' : 'Partially Received',
                        'acquisition_date' => $deliveryDate,
                        'acquisition_cost' => $unitPrice,
                        'received_at' => now(),
                    ]);


//                    $itemRequest->accountingEntries()->create([
//                        'initial_debit' => $itemRequest->accountingEntries->initialDebit->sync_id,
//                        'initial_credit' => $initialCreditId,
//                        'depreciation_credit' => $itemRequest->accountingEntries->depreciationCredit->sync_id,
//                    ]);

                    if ($itemRemaining == 0) {
                        $itemRequest->accountingEntries()->update([
                            'initial_credit' => $initialCreditId,
                        ]);
                    }

                    /*                    if ($itemRequest->item_id != null && $itemRequest->item_status == 'Replacement' && $itemRequest->fixed_asset_id != null) {
                                            $item = Item::where('id', $itemRequest->item_id)->first();
                                            $quantityReceived = $order['rr_orders']['quantity_received'];

                                            for ($i = 0; $i < $quantityReceived; $i++) {
                                                $assetSmallTools = AssetSmallTool::create([
                                                    'fixed_asset_id' => $itemRequest->fixed_asset_id,
                                                    'transaction_number' => $transactionNumber,
                                                    'reference_number' => $referenceNumber,
                                                    'receiving_warehouse_id' => $itemRequest->receiving_warehouse_id,
                                                    'small_tool_id' => $item->id,
                                                    'status_description' => 'Good',
                                                    'pr_number' => $prYearNumber,
                                                    'po_number' => $poNumber,
                                                    'rr_number' => $rrNumber,
                                                    'quantity' => 1,
                                                    'is_active' => 1,
                                                    'to_release' => 1,
                                                ]);
                                            }
                                        } else {
                                            $this->createNewAssetRequests($itemRequest, $order['rr_orders']['quantity_received'], $initialCreditId, $inclusion);
                                        }*/

                    $this->createNewAssetRequests($itemRequest, $order['rr_orders']['quantity_received'], $initialCreditId);
                    $rrNumbers[] = $order['rr_orders']['rr_number'];

                    $this->receivingLog($itemName, $order['rr_orders']['quantity_received'], $transactionNumber, $causer, $remaining);
                    $updateFilter = AssetRequest::where('transaction_number', $transactionNumber)
                        ->get();
                    foreach ($updateFilter as $filter) {
                        /*                     $filter->update([
                                                 'filter' => $remaining == 0 ? 'Asset Tagging' : 'Partially Received',
                                             ]);*/
                        if ($filter->item_id != null && $filter->item_status == 'Replacement' && $filter->fixed_asset_id != null) {
                            $filter->update([
                                'filter' => $remaining == 0 ? 'Ready to Pickup' : 'Partially Received',
                            ]);
                        } else {
                            $filter->update([
                                'filter' => $remaining == 0 ? 'Asset Tagging' : 'Partially Received',
                            ]);
                        }

                    }

                    $itemReceivedCount++;
//                    }
                    $rrNumberArray = [];
                    $this->updateRequestStatusFilter($itemRequest);
                }
            }
//            }

//        $this->storePOs($poData);
            if (!empty($rrNumbers)) {
                Http::withHeaders(['Token' => 'Bearer ' . $bearerToken])
                    ->put($apiUrl, ['rr_number' => $rrNumbers]);
            }

            $data = [
                'synced' => $itemReceivedCount,
                'cancelled' => $cancelledCount,
            ];
            DB::commit();
            return $this->responseSuccess('successfully sync received asset', $data);
        } catch
        (\Exception $e) {
            DB::rollBack();
            return $e;
            return $this->responseUnprocessable('something went wrong, please contact the support team');
        }

    }

    public function cancelRemaining(Request $request)
    {

        $transactionNumber = $request->transaction_no;
        $poNumber = $request->po_number;
        $causer = $request->causer;
        $reason = $request->reason;

        DB::beginTransaction();
        try {


            $assetRequest = AssetRequest::where('filter', 'Sent to Ymir')
                ->where('po_number', $poNumber)->get();

            if ($assetRequest->isEmpty()) {
                return $this->responseUnprocessable('No asset request found for transaction number ' . $transactionNumber);
            }

            foreach ($assetRequest as $aRequest) {
                if ($aRequest->quantity_delivered == 0) {
                    $aRequest->update([
                        'filter' => 'Cancelled',
                        'remarks' => $reason,
                    ]);
                    $this->receivingLog($request->description, $aRequest->quantity, $transactionNumber, $causer, $reason, true);
                    $aRequest->delete();
                    //skip the remaining code
                    continue;
                }

                $remaining = $aRequest->quantity - $aRequest->quantity_delivered;
                $replicatedAssetRequest = $aRequest->replicate();
                $replicatedAssetRequest->quantity = $remaining;
                $replicatedAssetRequest->quantity_delivered = 0;
                $replicatedAssetRequest->filter = NULL;
                $replicatedAssetRequest->remarks = $reason;
                $replicatedAssetRequest->save();
                $replicatedAssetRequest->delete();

                $aRequest->quantity -= $remaining;
                $aRequest->save();
                $this->receivingLog($request->description, $remaining, $transactionNumber, $causer, $reason, true);
            }

            DB::commit();

            return $this->responseSuccess('Successfully cancelled remaining items');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->responseUnprocessable('No asset request found for transaction number ' . $transactionNumber);
        }


    }

    /*public function cancelRemaining(Request $request)
    {

//        $userLocationId = auth('sanctum')->user()->location_id;
        $causer = $request->causer;
        $reason = $request->reason;
        $transactionNumber = $request->transaction_no;

        $assetRequest = AssetRequest::where('filter', 'Sent to Ymir')
            ->where('transaction_number', $transactionNumber)->get();
//            ->whereHas('receivingWarehouse', function ($query) use ($userLocationId) {
//                $query->where('location_id', $userLocationId);
//            })

        if ($assetRequest->isEmpty()) {
            return $this->responseUnprocessable('No asset request found for transaction number ' . $transactionNumber);
        }

        foreach ($assetRequest as $aRequest) {
            if ($aRequest->quantity_delivered == 0) {
                $aRequest->update([
                    'filter' => 'Cancelled',
                    'remarks' => $reason,
                ]);
                $this->receivingLog($request->description, $aRequest->quantity, $transactionNumber, $causer, $reason, true);
                $aRequest->delete();
                //skip the remaining code
                continue;
            }

            $remaining = $aRequest->quantity - $aRequest->quantity_delivered;
            $replicatedAssetRequest = $aRequest->replicate();
            $replicatedAssetRequest->quantity = $remaining;
            $replicatedAssetRequest->quantity_delivered = 0;
            $replicatedAssetRequest->filter = NULL;
            $replicatedAssetRequest->remarks = $reason;
            $replicatedAssetRequest->save();
            $replicatedAssetRequest->delete();

            $aRequest->quantity -= $remaining;
            $aRequest->save();
            $this->receivingLog($request->description, $remaining, $transactionNumber, $causer, $reason, true);
        }

        return $this->responseSuccess('Successfully cancelled remaining items');

    }*/


    public function receivingLog($itemName, $quantityDelivered, $transactionNumber, $causer, $remaining = null, $reason = null, $isCancelled = false)
    {
        $assetRequests = new AssetRequest();
        activity()
            ->performedOn($assetRequests)
            ->inLog($isCancelled ? 'Remaining Cancelled' : 'Item Received')
            ->withProperties(['asset_description' => $itemName,
                $isCancelled ? 'quantity_cancelled'
                    : 'quantity_delivered' => $quantityDelivered,
                'quantity_remaining' => $remaining,
                'causer' => $causer,
                'reason' => $reason
            ])
            ->tap(function ($activity) use ($transactionNumber) {
                $activity->subject_id = $transactionNumber;
            })
            ->log($isCancelled ? 'Remaining Cancelled' : 'Item Received');
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


    public function poCreatedActivity(Request $request)
    {
        $assetRequests = new AssetRequest();
        activity()
            ->performedOn($assetRequests)
            ->inLog('PO Created')
            ->withProperties([
                'po_number' => $request->po_number,
                'causer' => $request->causer,
            ])
            ->tap(function ($activity) use ($request) {
                $activity->subject_id = $request->transaction_number;
            })
            ->log('PO Created');

        $assetRequest = AssetRequest::where('transaction_number', $request->transaction_number)->get();
        foreach ($assetRequest as $ar) {
            $ar->update([
                'filter' => 'Po Created',
            ]);
        }

        return $this->responseSuccess('Activity Logged');
    }

    public function clientTest()
    {
        $apiUrl = 'http://10.10.10.15:9000/api/po-added';
        $bearerToken = '3267|RkELQ3StvYNa38PrAvnvJ6Mcd4deMLO4p9OOkteD';

        try {
            $response = Http::withHeaders(['Token' => 'Bearer ' . $bearerToken])
                ->patch(
                    $apiUrl,
                    [
                        "transaction_number" => "0002",
                        "causer" => "RDFFLFI-11143 Justine Dela Rosa",
                        "po_number" => "2025-PO-9203"
                    ]
                );

            if ($response->successful()) {
                return $this->responseSuccess('Request was successful');
            } else {
                return $this->responseUnprocessable('Request failed with status: ' . $response->status());
            }
        } catch (\Exception $e) {
//            \Log::error('Error in clientTest: ' . $e->getMessage());
            return $this->responseUnprocessable('An error occurred: ' . $e->getMessage());
        }
    }
}

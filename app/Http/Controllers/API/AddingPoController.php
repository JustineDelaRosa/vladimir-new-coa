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

        // Fetch all asset requests with eager loading to reduce queries
        $assetRequests = $this->createAssetRequestQuery($toPo, $from, $to)
            ->with(['receivingWarehouse', 'assetApproval', 'requestor'])
            ->get();

        // Get all transaction numbers in one go
        $transactionNumbers = $assetRequests->pluck('transaction_number')->unique()->toArray();

        // Fetch all cancelled (soft deleted) quantities in one query
        $cancelledQuantities = AssetRequest::onlyTrashed()
            ->whereIn('transaction_number', $transactionNumbers)
            ->select('transaction_number', DB::raw('SUM(quantity) as cancelled'))
            ->groupBy('transaction_number')
            ->pluck('cancelled', 'transaction_number')
            ->toArray();

        // Prefetch all activity logs for all transaction numbers in one query
        $activityLogs = \Spatie\Activitylog\Models\Activity::whereSubjectType(AssetRequest::class)
            ->whereIn('subject_id', $transactionNumbers)
            ->get()
            ->groupBy('subject_id');

        // Prefetch Ymir PR numbers for all PR numbers
        $prNumbers = $assetRequests->pluck('pr_number')->filter()->unique()->toArray();
        $ymirPrTransactions = [];
        if (!empty($prNumbers)) {
            $ymirPrTransactions = YmirPRTransaction::whereIn('pr_number', $prNumbers)
                ->pluck('pr_year_number_id', 'pr_number')
                ->toArray();
        }

        // Group and process in memory
        $assetRequest = $assetRequests->groupBy('transaction_number')
            ->map(function ($assetRequestCollection) use ($cancelledQuantities, $activityLogs, $ymirPrTransactions) {
                $assetRequest = $assetRequestCollection->first();
                $assetRequest->quantity = $assetRequestCollection->sum('quantity');
                $assetRequest->quantity_delivered = $assetRequestCollection->sum('quantity_delivered');
                $transactionNumber = $assetRequest->transaction_number;

                $cancelled = $cancelledQuantities[$transactionNumber] ?? 0;
                $anyRecentlyUpdated = $assetRequestCollection->contains(function ($item) {
                    return $item->updated_at->diffInMinutes(now()) < 2;
                });

                // Use array_unique and implode for better performance than collect()->unique()->implode()
                $poNumbers = array_filter(array_unique($assetRequestCollection->pluck('po_number')->toArray()));
                $poNumber = implode(',', $poNumbers);

                // More efficient approach to handle RR numbers without using array_merge in a loop
                $rrNumbers = array_filter(array_unique($assetRequestCollection->pluck('rr_number')->toArray()));
                $rrNumbersExploded = [];
                if (!empty($rrNumbers)) {
                    // Join all RR numbers with commas, then explode once
                    $allRrNumbers = implode(',', $rrNumbers);
                    $rrNumbersExploded = explode(',', $allRrNumbers);
                }
                $rrNumber = implode(',', array_unique($rrNumbersExploded));

                $assetRequest->po_number = $poNumber;
                $assetRequest->rr_number = $rrNumber;
                $assetRequest->cancelled = $cancelled;
                $assetRequest->newly_sync = $anyRecentlyUpdated ? 1 : 0;

                // Pass the prefetched data to reduce queries in transformIndexAssetRequest
                return $this->transformIndexAssetRequest($assetRequest, $activityLogs[$transactionNumber] ?? collect([]), $ymirPrTransactions);
            })->values();

        if ($perPage !== null) {
            $assetRequest = $this->paginate($request, $assetRequest, $perPage);
        }

        return $assetRequest;
    }

    //TODO: OLD INDEX
    /*public function index(Request $request)
        {
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

            // Fetch all asset requests
            $assetRequests = $this->createAssetRequestQuery($toPo, $from, $to)->get();

            // Get all transaction numbers in one go
            $transactionNumbers = $assetRequests->pluck('transaction_number')->unique();

            // Fetch all cancelled (soft deleted) quantities in one query
            $cancelledQuantities = AssetRequest::onlyTrashed()
                ->whereIn('transaction_number', $transactionNumbers)
                ->select('transaction_number', DB::raw('SUM(quantity) as cancelled'))
                ->groupBy('transaction_number')
                ->pluck('cancelled', 'transaction_number');

            // Group and process in memory
            $assetRequest = $assetRequests->groupBy('transaction_number')
                ->map(function ($assetRequestCollection) use ($cancelledQuantities) {
                $assetRequest = $assetRequestCollection->first();
                $assetRequest->quantity = $assetRequestCollection->sum('quantity');
                $assetRequest->quantity_delivered = $assetRequestCollection->sum('quantity_delivered');
                $transactionNumber = $assetRequest->transaction_number;

                $cancelled = $cancelledQuantities[$transactionNumber] ?? 0;
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
        }*/

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
            // Validate input data
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
            $rrNumberIdArray = [];

            // Extract common data
            $deletedAt = $data['cancelled_at'];
            $causer = $data['causer'] ?? null;
            $rrNumber = $data['rr_year_number_id'];

            // Collect all transaction numbers and reference numbers for batch processing
            $transactionNumbers = collect($data['orders'])->pluck('transaction_no')->unique()->toArray();
            $referenceNumbers = collect($data['orders'])->pluck('reference_no')->unique()->toArray();

            // Prefetch all asset requests in one query
            $allAssetRequests = AssetRequest::whereIn('filter', ['Sent to Ymir', 'Partially Received', 'Po Created', 'Asset Tagging'])
                ->whereIn('transaction_number', $transactionNumbers)
                ->get()
                ->groupBy('transaction_number');

            // Prefetch all item requests in one query
            $allItemRequests = AssetRequest::whereIn('transaction_number', $transactionNumbers)
                ->whereIn('reference_number', $referenceNumbers)
                ->get()
                ->keyBy(function ($item) {
                    return $item->transaction_number . '-' . $item->reference_number;
                });

            // Prefetch suppliers
            $supplierIds = collect($data['orders'])->pluck('supplier')->unique()->toArray();
            $suppliers = Supplier::whereIn('sync_id', $supplierIds)->get()->keyBy('sync_id');

            foreach ($data['orders'] as $order) {
                $supplier = $order['supplier'];
                $unitPrice = $order['unit_price'];
                $transactionNumber = $order['transaction_no'];
                $referenceNumber = $order['reference_no'];
                $remaining = $order['remaining'];
                $itemName = $order['item_name'];
                $quantityDelivered = $order['quantity_delivered'];

                // Skip if no asset requests found for this transaction
                $assetRequest = $allAssetRequests[$transactionNumber] ?? collect([]);
                if ($assetRequest->isEmpty()) {
                    continue;
                }

                // Get the item request using the composite key
                $itemRequestKey = $transactionNumber . '-' . $referenceNumber;
                $itemRequest = $allItemRequests[$itemRequestKey] ?? null;
                if (!$itemRequest) {
                    continue;
                }

                $rrNumberArray = [];
                $poNumberArray = [];

                // Extract RR order data
                $rrOrderData = $order['rr_orders'];
                $deliveryDate = $rrOrderData['delivery_date'];
                $itemRemaining = $rrOrderData['remaining'];
                $rrNumberId = $rrOrderData['rr_number'];
                $prYearNumber = $rrOrderData['pr_year_number_id'];
                $poNumber = $rrOrderData['po_year_number_id'];
                $initialCreditId = $rrOrderData['initial_credit_id'];
                $ymirReferenceNumber = $rrOrderData['shipment_no'];
                $quantityReceived = $rrOrderData['quantity_received'];

                // Skip if already synced
                if ($rrOrderData['sync'] == 1) {
                    continue;
                }

                // Process RR numbers
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

                // Calculate total quantity delivered
                $totalQuantityDelivered = $itemRequest->quantity_delivered + $quantityReceived;

                // Check supplier
                $supplierCheck = $suppliers[$supplier] ?? null;
                if ($supplierCheck == null) {
                    return $this->responseUnprocessable('Supplier not found, Please sync vladimir Supplier first');
                }

                // Update item request
                $itemRequest->update([
                    'synced' => 1,
                    'po_number' => $poNumberString,
                    'rr_number' => $rrNumberString,
                    'rr_id' => $rrNumberIdString,
                    'ymir_pr_number' => $prYearNumber,
                    'supplier_id' => $supplier,
                    'quantity' => $itemRequest->quantity,
                    'quantity_delivered' => $totalQuantityDelivered,
                    'acquisition_date' => $deliveryDate,
                    'acquisition_cost' => $unitPrice,
                    'received_at' => now(),
                ]);

                // Update accounting entries if remaining is 0
                if ($itemRemaining == 0) {
                    $itemRequest->accountingEntries()->update([
                        'initial_credit' => $initialCreditId,
                    ]);
                }

                // Create new asset requests
                $this->createNewAssetRequests($itemRequest, $quantityReceived, $initialCreditId, $ymirReferenceNumber);
                $rrNumbers[] = $rrOrderData['rr_number'];

                // Log the receiving
                $this->receivingLog($itemName, $quantityReceived, $transactionNumber, $causer, $remaining);

                // Update filters in batch
                $updateFilters = [];
                foreach ($assetRequest as $filter) {
                    $newFilter = $remaining == 0 ?
                        ($filter->item_id != null && $filter->item_status == 'Replacement' && $filter->fixed_asset_id != null ?
                            'Ready to Pickup' : 'Asset Tagging') :
                        'Partially Received';

                    $filter->filter = $newFilter;
                    $updateFilters[] = $filter;
                }

                // Bulk update filters
                if (!empty($updateFilters)) {
                    DB::table('asset_requests')
                        ->whereIn('id', collect($updateFilters)->pluck('id')->toArray())
                        ->update(['filter' => DB::raw("CASE 
                            WHEN item_id IS NOT NULL AND item_status = 'Replacement' AND fixed_asset_id IS NOT NULL THEN 
                                CASE WHEN $remaining = 0 THEN 'Ready to Pickup' ELSE 'Partially Received' END
                            ELSE 
                                CASE WHEN $remaining = 0 THEN 'Asset Tagging' ELSE 'Partially Received' END
                            END")]);
                }

                $itemReceivedCount++;
                $rrNumberArray = [];
                $this->updateRequestStatusFilter($itemRequest);
            }

            // Send API request if there are RR numbers
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
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->responseUnprocessable('something went wrong, please contact the support team: ' . $e->getMessage());
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
            // Use a more efficient query with proper parameter binding
            $assetRequests = AssetRequest::where('filter', 'Partially Received')
                ->where('po_number', 'like', '%' . $poNumber . '%')
                ->get();

            if ($assetRequests->isEmpty()) {
                return $this->responseUnprocessable('No asset request found for transaction number ' . $transactionNumber);
            }

            // Prepare data for bulk operations
            $toUpdate = [];
            $toDelete = [];
            $toReplicate = [];
            $logEntries = [];

            foreach ($assetRequests as $aRequest) {
                if ($aRequest->quantity_delivered == 0) {
                    // Mark for update and deletion
                    $aRequest->filter = 'Cancelled';
                    $aRequest->remarks = $reason;
                    $toUpdate[] = $aRequest;
                    $toDelete[] = $aRequest->id;

                    // Prepare log entry
                    $logEntries[] = [
                        'description' => $request->description,
                        'quantity' => $aRequest->quantity,
                        'transaction_number' => $transactionNumber,
                        'causer' => $causer,
                        'reason' => $reason,
                        'is_cancelled' => true
                    ];

                    continue;
                }

                $remaining = $aRequest->quantity - $aRequest->quantity_delivered;

                // Create replicated request data
                $replicatedData = $aRequest->replicate();
                $replicatedData->quantity = $remaining;
                $replicatedData->quantity_delivered = 0;
                $replicatedData->filter = NULL;
                $replicatedData->remarks = $reason;
                $toReplicate[] = $replicatedData;

                // Update original request
                $aRequest->quantity -= $remaining;
                $toUpdate[] = $aRequest;

                // Prepare log entry
                $logEntries[] = [
                    'description' => $request->description,
                    'quantity' => $remaining,
                    'transaction_number' => $transactionNumber,
                    'causer' => $causer,
                    'reason' => $reason,
                    'is_cancelled' => true
                ];
            }

            // Perform bulk operations
            if (!empty($toUpdate)) {
                foreach ($toUpdate as $item) {
                    $item->save();
                }
            }

            if (!empty($toDelete)) {
                AssetRequest::whereIn('id', $toDelete)->delete();
            }

            if (!empty($toReplicate)) {
                foreach ($toReplicate as $item) {
                    $item->save();
                    $item->delete();
                }
            }

            // Create log entries
            foreach ($logEntries as $entry) {
                $this->receivingLog(
                    $entry['description'],
                    $entry['quantity'],
                    $entry['transaction_number'],
                    $entry['causer'],
                    $entry['reason'],
                    $entry['is_cancelled']
                );
            }

            DB::commit();
            return $this->responseSuccess('Successfully cancelled remaining items');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->responseUnprocessable('Error processing cancellation: ' . $e->getMessage());
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

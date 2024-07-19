<?php

namespace App\Traits;

use App\Models\AdditionalCost;
use App\Models\AssetRequest;
use App\Models\FixedAsset;
use App\Models\Formula;
use App\Models\Status\AssetStatus;
use App\Models\Status\CycleCountStatus;
use App\Models\Status\MovementStatus;
use App\Models\WarehouseNumber;
use App\Repositories\AdditionalCostRepository;
use App\Repositories\VladimirTagGeneratorRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

trait AddingPoHandler
{

    protected VladimirTagGeneratorRepository $vladimirTagGeneratorRepository;
    protected AdditionalCostRepository $additionalCostRepository;

    public function __construct()
    {
        $this->additionalCostRepository = new AdditionalCostRepository();
        $this->vladimirTagGeneratorRepository = new VladimirTagGeneratorRepository();
    }

    public function createAssetRequestQuery($toPo)
    {
        $userLocationId = auth('sanctum')->user()->location_id;
        $query = AssetRequest::query()->withTrashed();

        $query->where('status', 'Approved')
            ->where('is_fa_approved', 1)
            ->whereHas('receivingWarehouse', function ($query) use($userLocationId) {
                $query->where('location_id', $userLocationId);
            })
            ->whereNull('deleted_at');

        if ($toPo !== null) {
            $query->whereHasTransactionNumberSynced($toPo);
        }

        $query->orderBy('created_at', 'desc')
            ->useFilters();

        return $query;
    }

    public function paginate($request, $assetRequest, $perPage)
    {
        $page = $request->input('page', 1);
        $offset = $page * $perPage - $perPage;

        return new LengthAwarePaginator(
            $assetRequest->slice($offset, $perPage)->values(),
            $assetRequest->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );
    }

    public function activityLogPo($assetRequest, $poNumber, $rrNumber, $removedCount, $removeRemaining = false, $remove = false)
    {
        $user = auth('sanctum')->user();
        $assetRequests = new AssetRequest();
        // dd($remove);
        activity()
            ->causedBy($user)
            ->performedOn($assetRequests)
            ->withProperties($this->composeLogPropertiesPo($assetRequest, $poNumber, $rrNumber, $removedCount, $removeRemaining, $remove))
            ->inLog(($removeRemaining == true) ? 'Cancelled Remaining Items' : (($remove == true) ? 'Cancelled Item To PO' : 'Added PO Number and RR Number'))
            ->tap(function ($activity) use ($user, $assetRequest, $poNumber, $rrNumber) {
                $firstAssetRequest = $assetRequest;
                if ($firstAssetRequest) {
                    $activity->subject_id = $firstAssetRequest->transaction_number;
                }
            })
            ->log($removeRemaining === true ? 'Remaining item to items was cancelled by ' . $user->employee_id . '.' : ($remove === true ?
                'Item was cancelled by ' . $user->employee_id . '.' : 'PO Number: ' . $poNumber . ' has been added by ' . $user->employee_id . '.'));
    }

    private function composeLogPropertiesPo($assetRequest, $poNumber = null, $rrNumber = null, $removedCount = 0, $removeRemaining = false, $remove = false): array
    {
        $requestor = $assetRequest->requestor;
        $remaining = $this->calculateRemainingQuantity($assetRequest->transaction_number, true);
        return [
            'requestor' => [
                'id' => $requestor->id,
                'firstname' => $requestor->firstname,
                'lastname' => $requestor->lastname,
                'employee_id' => $requestor->employee_id,
            ],
            'remaining_to_po' => ($remaining == 0) ? 0 : (($removeRemaining == true) ? ($remaining - $removedCount) : $remaining),
            'quantity_removed' => ($removeRemaining == true) ? $removedCount : (($remove == true) ? $assetRequest->quantity : null),
            'po_number' => $poNumber ?? null,
            'rr_number' => $rrNumber ?? null,
            'remarks' => $assetRequest->remarks,
            'description' => $assetRequest->asset_description,
        ];
    }

    private function quantityRemovedHolder($assetRequest)
    {
        //hold the removed quantity for 'quantity_removed' to be used in activity log
        $removedQuantity = 0;
        if ($assetRequest->quantity_delivered > 0) {
            $removedQuantity = $assetRequest->quantity - $assetRequest->quantity_delivered;
        } else {
            $removedQuantity = $assetRequest->quantity;
        }
        return $removedQuantity;
    }

    public function calculateRemainingQuantity($transactionNumber, $forTimeline = false)
    {

        $items = AssetRequest::where('transaction_number', $transactionNumber)->get();
        $remainingQuantities = $items->map(function ($item) {
            $remaining = $item->quantity - $item->quantity_delivered;
            if ($forTimeline = false) {
                $remaining = $remaining . "/" . $item->quantity;
            }

            return $remaining;
        });
        if ($forTimeline = false) {
            $remainingQuantities = $remainingQuantities->reduce(function ($carry, $item) {
                $carry = explode("/", $carry);
                $item = explode("/", $item);
                $carry[0] = $carry[0] + $item[0];
                $carry[1] = $carry[1] + $item[1];
                return $carry[0] . "/" . $carry[1];
            }, "0/0");
        } else {
            $remainingQuantities = $remainingQuantities->reduce(function ($carry, $item) {
                return $carry + $item;
            }, 0);
        }

        return $remainingQuantities;
    }

    public function updatePoAssetRequest($assetRequest, $request)
    {
        $printCount = $this->calculatePrintCount($assetRequest, $request);

        $assetRequest->update([
            'po_number' => $request->po_number,
            'rr_number' => $request->rr_number,
            'supplier_id' => $request->supplier_id,
            'acquisition_date' => $request->delivery_date,
            'quantity_delivered' => $assetRequest->quantity_delivered + $request->quantity_delivered,
            'acquisition_cost' => $request->unit_price,
            'print_count' => $assetRequest->print_count + $printCount,
        ]);
    }

    public function updateRequestStatusFilter($assetRequest)
    {
        // Get all items in the request for this transaction number
        $allItemInRequest = AssetRequest::where('transaction_number', $assetRequest->transaction_number)->get();

        // Get the count of all items from FixedAsset and AdditionalCost for this transaction number
        $fixedAssetCount = FixedAsset::where('transaction_number', $assetRequest->transaction_number)->count();
        $additionalCostCount = AdditionalCost::where('transaction_number', $assetRequest->transaction_number)->count();
        // Get the total quantity of all items in the request
        $totalQuantity = $allItemInRequest->sum('quantity');

        // Determine the filter status based on the counts
        $filterStatus = null;
        if ($fixedAssetCount == $totalQuantity) {
            $filterStatus = 'Received';
        } elseif ($additionalCostCount == $totalQuantity) {
            $filterStatus = 'Ready to Pickup';
        }

        // If a filter status was determined, update all items in the request
        if ($filterStatus) {
            $allItemInRequest->each(function($item) use ($filterStatus) {
                $item->update(['filter' => $filterStatus]);
            });
        }
//        dd("$filterStatus - $totalQuantity - $fixedAssetCount - $additionalCostCount");
    }

    private function calculatePrintCount($assetRequest, $request)
    {
        if ($assetRequest->is_addcost == 1) {
            $printCount = FixedAsset::where('id', $assetRequest->fixed_asset_id)->first()->print_count;
            return $printCount > 0 ? $request->quantity_delivered : 0;
        }

        return 0;
    }

    public function getAllItems($assetId, $quantityDelivered)
    {
        $assetRequest = AssetRequest::find($assetId);

        if ($assetRequest && $assetRequest->status == 'Approved') {
            $this->createNewAssetRequests($assetRequest, $quantityDelivered);
        }
    }

    private function createNewAssetRequests($assetRequest, $quantityDelivered)
    {
        if ($assetRequest->quantity > 1) {
            foreach (range(1, $quantityDelivered) as $index) {
                $this->addToFixedAssets($assetRequest, $assetRequest->is_addcost);
            }
        } else {
            $this->addToFixedAssets($assetRequest, $assetRequest->is_addcost);
        }

    }


    private function addToFixedAssets($asset, $isAddCost)
    {
        if ($isAddCost == 1) {
            $formula = Formula::create([
                'depreciation_method' => $asset->depreciation_method,
                'acquisition_date' => $asset->acquisition_date,
                'acquisition_cost' => $asset->acquisition_cost,
                'scrap_value' => 0,
                'months_depreciated' => 0,
            ]);
            $warehouseNumber = new WarehouseNumber();
            $warehouseNumber->save();
            $generateWhNumber = $warehouseNumber->generateWhNumber();
            $warehouseNumber->update([
                'warehouse_number' => $generateWhNumber,
            ]);
            $formula->additionalCost()->create([
                'requester_id' => $asset->requester_id,
                'reference_number' => $asset->reference_number,
                'uom_id' => $asset->uom_id,
                'pr_number' => $asset->pr_number,
                'po_number' => $asset->po_number,
                'receipt' => $asset->rr_number,
                'rr_number' => $asset->rr_number,
                'warehouse_id' => $asset->receiving_warehouse_id,
                'warehouse_number_id' => $warehouseNumber->id,
                'fixed_asset_id' => $asset->fixed_asset_id,
                'from_request' => 1,
                'can_release' => 1,
                'add_cost_sequence' => $this->additionalCostRepository->getAddCostSequence($asset->fixed_asset_id) ?? '-',
                'transaction_number' => $asset->transaction_number,
                'asset_description' => $asset->asset_description,
                'type_of_request_id' => $asset->type_of_request_id,
//                'charged_department' => $asset->department_id,
                'asset_specification' => $asset->asset_specification,
                'supplier_id' => $asset->supplier_id,
                'accountability' => $asset->accountability,
                'accountable' => $asset->accountable ?? '-',
                'cellphone_number' => $asset->cellphone_number ?? '-',
                'brand' => $asset->brand,
                'quantity' => 1,
                'depreciation_method' => $asset->depreciation_method,
                'acquisition_date' => $asset->acquisition_date,
                'acquisition_cost' => $asset->acquisition_cost,
                'asset_status_id' => AssetStatus::where('asset_status_name', 'Good')->first()->id,
//                'is_old_asset' => 0,
                'is_additional_cost' => $asset->is_addcost,
                'company_id' => $asset->company_id,
                'business_unit_id' => $asset->business_unit_id,
                'department_id' => $asset->department_id,
                'unit_id' => $asset->unit_id,
                'subunit_id' => $asset->subunit_id,
                'location_id' => $asset->location_id,
                'account_id' => $asset->account_title_id,
                'remarks' => $asset->remarks,
                'movement_status_id' => MovementStatus::where('movement_status_name', 'New')->first()->id,
                'cycle_count_status_id' => CycleCountStatus::where('cycle_count_status_name', 'On Site')->first()->id,

            ]);

        } else {
            $formula = Formula::create([
                'depreciation_method' => $asset->depreciation_method,
                'acquisition_date' => $asset->acquisition_date,
                'acquisition_cost' => $asset->acquisition_cost,
                'scrap_value' => 0,
                'months_depreciated' => 0,
            ]);
            $warehouseNumber = new WarehouseNumber();
            $warehouseNumber->save(); // Save the WarehouseNumber instance to the database to generate an id
            $generateWhNumber = $warehouseNumber->generateWhNumber(); // Now you can call generateWhNumber
            $warehouseNumber->update([
                'warehouse_number' => $generateWhNumber,
            ]);
            $fixedAsset = $formula->fixedAsset()->create([
                'requester_id' => $asset->requester_id,
                'reference_number' => $asset->reference_number,
                'uom_id' => $asset->uom_id,
                'pr_number' => $asset->pr_number,
                'po_number' => $asset->po_number,
                'rr_number' => $asset->rr_number,
                'receipt' => $asset->rr_number,
                'capex_number' => $asset->capex_number,
                'warehouse_id' => $asset->receiving_warehouse_id,
                'warehouse_number_id' => $warehouseNumber->id,
                'from_request' => 1,
                'transaction_number' => $asset->transaction_number,
                'asset_description' => $asset->asset_description,
                'type_of_request_id' => $asset->type_of_request_id,
                'charged_department' => $asset->department_id,
                'asset_specification' => $asset->asset_specification,
                'supplier_id' => $asset->supplier_id,
                'accountability' => $asset->accountability,
                'accountable' => $asset->accountable ?? '-',
                'cellphone_number' => $asset->cellphone_number ?? '-',
                'brand' => $asset->brand,
                'quantity' => 1,
                'depreciation_method' => $asset->depreciation_method,
                'acquisition_date' => $asset->acquisition_date,
                'acquisition_cost' => $asset->acquisition_cost,
                'asset_status_id' => AssetStatus::where('asset_status_name', 'Good')->first()->id,
                'is_old_asset' => 0,
                'is_additional_cost' => $asset->is_addcost,
                'company_id' => $asset->company_id,
                'business_unit_id' => $asset->business_unit_id,
                'department_id' => $asset->department_id,
                'unit_id' => $asset->unit_id,
                'subunit_id' => $asset->subunit_id,
                'location_id' => $asset->location_id,
                'account_id' => $asset->account_title_id,
                'remarks' => $asset->remarks,
                'movement_status_id' => MovementStatus::where('movement_status_name', 'New')->first()->id,
                'cycle_count_status_id' => CycleCountStatus::where('cycle_count_status_name', 'On Site')->first()->id,
            ]);
            $fixedAsset->vladimir_tag_number = $this->vladimirTagGeneratorRepository->vladimirTagGenerator();
            $fixedAsset->save();
        }
    }

    public function deleteAssetRequestPo($assetRequest, $remarks)
    {
        if ($assetRequest instanceof \Illuminate\Database\Eloquent\Collection) {
            $this->activityLogPo($assetRequest->first(), $assetRequest->first()->po_number, $assetRequest->first()->rr_number, 0, false, true);
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            $assetRequest->each(function ($request) use ($remarks) {
                $request->deleter_id = auth('sanctum')->user()->id;
                $request->remarks = $remarks;
                $request->save();
                $request->delete();
            });
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        } else {
            $this->activityLogPo($assetRequest, $assetRequest->po_number ?? null, $assetRequest->rr_number ?? null, 0, false, true);
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            $assetRequest->deleter_id = auth('sanctum')->user()->id;
            $assetRequest->remarks = $remarks;
            $assetRequest->save();
            $assetRequest->delete();
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }
    }

    public function handleQuantityMismatch($assetRequest, $remarks)
    {
        $removedQuantity = $this->quantityRemovedHolder($assetRequest);
        $storedRemovedQuantity = $removedQuantity;

        // Check if the quantity and quantity delivered are not equal
        if ($assetRequest->quantity != $assetRequest->quantity_delivered) {
            // Calculate the remaining quantity
            $remainingQuantity = $assetRequest->quantity - $assetRequest->quantity_delivered;

            // Create a duplicate asset request with the remaining quantity
            $duplicateAssetRequest = $assetRequest->replicate();
            $duplicateAssetRequest->quantity = $remainingQuantity;
            $duplicateAssetRequest->po_number = null;
            $duplicateAssetRequest->rr_number = null;
            $duplicateAssetRequest->quantity_delivered = 0;
            $duplicateAssetRequest->remarks = $remarks;
            $duplicateAssetRequest->deleter_id = auth('sanctum')->user()->id;
//            $duplicateAssetRequest->reference_number = $duplicateAssetRequest->generateReferenceNumber();

            // Save the duplicate asset request
            $duplicateAssetRequest->save();

            // Delete the duplicate asset request
            $duplicateAssetRequest->delete();
        }

        $assetRequest->quantity = $assetRequest->quantity_delivered;
        $assetRequest->save();
        $this->activityLogPo($assetRequest, $assetRequest->po_number, $assetRequest->rr_number, $storedRemovedQuantity, true, false);
        $remaining = $this->calculateRemainingQuantity($assetRequest->transaction_number, false);
        if ($remaining == 0) {
//            $this->getAllItems($assetRequest->transaction_number, $storedRemovedQuantity);
            $this->updateFilterStatus($assetRequest->transaction_number);
        }

        return $this->responseSuccess(
             'Remaining quantity removed successfully!',
            ['total_remaining' => $remaining ?? 0]
        );
    }

//TODO:FOR SOFT DELETING

//    public function handleQuantityMismatch($assetRequest)
//    {
//        $removedQuantity = $this->quantityRemovedHolder($assetRequest);
//        $storedRemovedQuantity = $removedQuantity;
//
//        $remaining = $this->calculateRemainingQuantity($assetRequest->transaction_number, $forTimeline = false);
//
//        if ($remaining > 0) {
//            // Create a duplicate asset request with the remaining quantity
//            $duplicateAssetRequest = $assetRequest->replicate();
//            $duplicateAssetRequest->quantity = $remaining;
//            $duplicateAssetRequest->save();
//        }
//
//        // Update the original asset request's quantity to the delivered quantity
//        $assetRequest->quantity = $assetRequest->quantity_delivered;
//        $assetRequest->save();
//
//        // Log this activity
//        $this->activityLogPo($assetRequest, $assetRequest->po_number, $assetRequest->rr_number, $storedRemovedQuantity, true, false);
//
//        if ($remaining == 0) {
//            $this->getAllItems($assetRequest->transaction_number, $storedRemovedQuantity);
//        }
//
//        // Delete the duplicate asset request if it exists
//        if (isset($duplicateAssetRequest)) {
//            $duplicateAssetRequest->delete();
//        }
//
//        return $this->responseSuccess('Remaining quantity removed successfully!');
//    }

    //FOR DELETING
    private function handleTransactionNumberCase($transactionNumber): JsonResponse
    {
        $assetRequest = AssetRequest::where('transaction_number', $transactionNumber)->get();
        $remainingCount = 0;
        //get the remaining quantity to be ordered for the transaction number
        foreach ($assetRequest as $asset) {
            $remainingCount += $asset->quantity - $asset->quantity_delivered;
        }

        if ($assetRequest->isEmpty()) {
            return $this->responseNotFound('Asset Request not found!');
        }

        foreach ($assetRequest as $asset) {
            if ($asset->quantity_delivered !== null && $asset->quantity_delivered !== 0) {
                return $this->responseUnprocessable('Cannot remove request, some items has been delivered!');
            }
        }

        $this->deleteAssetRequestPo($assetRequest);

        return $this->responseSuccess(
            'Item removed successfully!',
            ['total_remaining' => $remainingCount]
        );
    }

    private function handleIdCase($id, $remarks)
    {
        $assetRequest = AssetRequest::find($id);
        if (!$assetRequest) {
            return $this->responseNotFound('Asset Request not found!');
        }

        if ($assetRequest->quantity_delivered == null || $assetRequest->quantity_delivered == 0) {
            $assetRequest->remarks = $remarks;
            $assetRequest->save();
            $this->deleteAssetRequestPo($assetRequest, $remarks);
            $remainingCount = $this->calculateRemainingQuantity($assetRequest->transaction_number);

            if($remainingCount == 0){
                $this->updateFilterStatus($assetRequest->transaction_number);
            }

            return $this->responseSuccess('Item removed successfully!', ['total_remaining' => $remainingCount]);
        }

        if ($assetRequest->quantity !== $assetRequest->quantity_delivered) {
            return $this->handleQuantityMismatch($assetRequest, $remarks);
        }

        return $this->responseUnprocessable('Item cannot be removed!');
    }


    private function updateFilterStatus($transactionNumber)
    {
        //Add the withTrashed if needed
        $assetRequests = AssetRequest::withTrashed()->where('transaction_number', $transactionNumber)->get();
        $isAddCost = $assetRequests->first()->is_addcost ?? 0;
        $filterStatus = $isAddCost == 1 ? 'Ready to Pickup' : 'Received';

        foreach ($assetRequests as $assetRequest) {
            $assetRequest->update(['filter' => $filterStatus]);
        }
    }

    protected function validateSyncData($data, $apiUrl, $bearerToken): ?JsonResponse
    {
        if ($data === null) {
            return $this->responseUnprocessable('No data to sync');
        }
        if (is_null($apiUrl) || is_null($bearerToken)) {
            return $this->responseUnprocessable('API URL or Bearer Token is not configured properly.');
        }
        return null;
    }

    protected function processAsset($asset, $userLocationId, $bearerToken, $apiUrl)
    {
        $transactionNumber = $asset['transaction_no'];
        $assetRequest = AssetRequest::where('filter', 'Sent to Ymir')
            ->where('transaction_number', $transactionNumber)
            ->where('reference_number', $asset['order']['item_code'])
            ->whereHas('receivingWarehouse', function ($query) use ($userLocationId) {
                $query->where('location_id', $userLocationId);
            })->first();

        if (!$assetRequest) {
            return;
        }

        foreach ($asset['rr_orders'] as $rrOrder) {
            $assetRequest->update([
                'synced' => 1,
                'pr_number' => $asset['po_number'],
                'po_number' => $asset['pr_number'],
                'rr_number' => $rrOrder['rr_number'],
                'supplier_id' => $asset['order']['supplier'],
                'quantity_delivered' => $assetRequest->quantity_delivered + $rrOrder['quantity_receive'],
                'acquisition_date' => $rrOrder['delivery_date'],
                'acquisition_cost' => $asset['order']['unit_price'],
            ]);
            $this->createNewAssetRequests($assetRequest, $rrOrder['quantity_receive']);
            Http::withHeaders(['Authorization' => 'Bearer ' . $bearerToken,])->put($apiUrl, ['rr_number' => $rrOrder['rr_number']]);
        }
        $this->updateRequestStatusFilter($assetRequest);
    }
}

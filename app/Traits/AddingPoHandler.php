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
        return AssetRequest::where('status', 'Approved')
            ->wherenotnull('pr_number')
            ->when($toPo !== null, function ($query) use ($toPo) {
                return $query->whereNotIn('transaction_number', function ($query) use ($toPo) {
                    $query->select('transaction_number')
                        ->from('asset_requests')
                        ->groupBy('transaction_number')
                        ->havingRaw('SUM(quantity) ' . ($toPo == 1 ? '=' : '!=') . ' SUM(quantity_delivered)');
                });
            })
            ->useFilters();
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

    public function activityLogPo($assetRequest, $poNumber, $rrnumber, $removedCount, $removeRemaining = false, $remove = false)
    {
        $user = auth('sanctum')->user();
        $assetRequests = new AssetRequest();
        // dd($remove);
        activity()
            ->causedBy($user)
            ->performedOn($assetRequests)
            ->withProperties($this->composeLogPropertiesPo($assetRequest, $poNumber, $rrnumber, $removedCount, $removeRemaining, $remove))
            ->inLog(($removeRemaining == true) ? 'Removed Remaining Items' : (($remove == true) ? 'Removed Item To PO' : 'Added PO Number and RR Number'))
            ->tap(function ($activity) use ($user, $assetRequest, $poNumber, $rrnumber) {
                $firstAssetRequest = $assetRequest;
                if ($firstAssetRequest) {
                    $activity->subject_id = $firstAssetRequest->transaction_number;
                }
            })
            ->log($removeRemaining === true ? 'Remaining item to items was removed by ' . $user->employee_id . '.' : ($remove === true ?
                'Item was removed by ' . $user->employee_id . '.' : 'PO Number: ' . $poNumber . ' has been added by ' . $user->employee_id . '.'));
    }

    private function composeLogPropertiesPo($assetRequest, $poNumber = null, $rrnumber = null, $removedCount = 0, $removeRemaining = false, $remove = false): array
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
            'rr_number' => $rrnumber ?? null,
            'remarks' => null,
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

    private function calculatePrintCount($assetRequest, $request)
    {
        if($assetRequest->is_addcost == 1){
            $printCount = FixedAsset::where('id', $assetRequest->fixed_asset_id)->first()->print_count;
            return $printCount > 0 ? $request->quantity_delivered : 0;
        }

        return 0;
    }

    private function getAllItems($assetId, $quantityDelivered)
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
            ]);
            $warehouseNumber = new WarehouseNumber();
            $warehouseNumber->save();
            $generateWhNumber = $warehouseNumber->generateWhNumber();
            $warehouseNumber->update([
                'warehouse_number' => $generateWhNumber,
            ]);
            $formula->additionalCost()->create([
                'requester_id' => $asset->requester_id,
                'pr_number' => $asset->pr_number,
                'po_number' => $asset->po_number,
                'receipt' => $asset->rr_number,
                'rr_number' => $asset->rr_number,
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
            ]);
            $warehouseNumber = new WarehouseNumber();
            $warehouseNumber->save(); // Save the WarehouseNumber instance to the database to generate an id
            $generateWhNumber = $warehouseNumber->generateWhNumber(); // Now you can call generateWhNumber
            $warehouseNumber->update([
                'warehouse_number' => $generateWhNumber,
            ]);
            $fixedAsset = $formula->fixedAsset()->create([
                'requester_id' => $asset->requester_id,
                'pr_number' => $asset->pr_number,
                'po_number' => $asset->po_number,
                'rr_number' => $asset->rr_number,
                'receipt' => $asset->rr_number,
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

    public function deleteAssetRequestPo($assetRequest)
    {
        if ($assetRequest instanceof \Illuminate\Database\Eloquent\Collection) {
            $this->activityLogPo($assetRequest->first(), $assetRequest->first()->po_number, $assetRequest->first()->rr_number, 0, false, true);
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            $assetRequest->each(function ($request) {
                $request->delete();
            });
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        } else {
            $this->activityLogPo($assetRequest, $assetRequest->po_number ?? null, $assetRequest->rr_number ?? null, 0, false, true);
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            $assetRequest->delete();
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }
    }

    public function handleQuantityMismatch($assetRequest)
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
            $duplicateAssetRequest->quantity_delivered = 0;
            $duplicateAssetRequest->deleter_id = auth('sanctum')->user()->id;
            $duplicateAssetRequest->reference_number = $duplicateAssetRequest->generateReferenceNumber();

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
            $this->getAllItems($assetRequest->transaction_number, $storedRemovedQuantity);
        }
        return $this->responseSuccess('Remaining quantity removed successfully!');
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

        if ($assetRequest->isEmpty()) {
            return $this->responseNotFound('Asset Request not found!');
        }

        foreach ($assetRequest as $asset) {
            if ($asset->quantity_delivered !== null && $asset->quantity_delivered !== 0) {
                return $this->responseUnprocessable('Cannot remove request, some items has been delivered!');
            }
        }

        $this->deleteAssetRequestPo($assetRequest);

        return $this->responseSuccess('Item removed successfully!');
    }

    private function handleIdCase($id)
    {
        $assetRequest = AssetRequest::where('id', $id)->first();

        if (!$assetRequest) {
            return $this->responseNotFound('Asset Request not found!');
        }

//        $assetRequestCheck = AssetRequest::where('transaction_number', $assetRequest->transaction_number)->get();

//        if ($assetRequestCheck->count() == 1) {
        //            return $this->responseUnprocessable('Cannot remove final item');
        //        }

        if ($assetRequest->quantity_delivered == null || $assetRequest->quantity_delivered == 0) {
            $this->deleteAssetRequestPo($assetRequest);
            return $this->responseSuccess('Item removed successfully!');
        }

        if ($assetRequest->quantity !== $assetRequest->quantity_delivered) {
            return $this->handleQuantityMismatch($assetRequest);
        }

        return $this->responseUnprocessable('Item cannot be removed!');
    }
}

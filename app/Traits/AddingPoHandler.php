<?php

namespace App\Traits;

use App\Models\Formula;
use App\Models\FixedAsset;
use App\Models\AssetRequest;
use App\Models\Status\AssetStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Repositories\VladimirTagGeneratorRepository;

trait AddingPoHandler
{

    protected $vladimirTagGeneratorRepository;

    public function __construct()
    {
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
            ->log($removeRemaining === true ? 'Remaining item to items was removed by ' . $user->employee_id . '.' : $remove === true ?
                'Item was removed by ' . $user->employee_id . '.' : 'PO Number: ' . $poNumber . ' has been added by ' . $user->employee_id . '.');
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
        $assetRequest->update([
            'po_number' => $assetRequest->po_number ? $assetRequest->po_number : $request->po_number,
            'rr_number' => $assetRequest->rr_number ? $assetRequest->rr_number : $request->rr_number,
            'supplier_id' => $assetRequest->supplier_id ? $assetRequest->supplier_id : $request->supplier_id,
            'acquisition_date' => $assetRequest->acquisition_date ? $assetRequest->acquisition_date : $request->delivery_date,
            'quantity_delivered' => $assetRequest->quantity_delivered + $request->quantity_delivered,
            'acquisition_cost' => $assetRequest->acquisition_cost ? $assetRequest->acquisition_cost : $request->unit_price,
        ]);

        // $this->createNewAssetRequests($assetRequest);
    }

    private function getAllitems($transactionNumber)
    {
        $assetRequests = AssetRequest::where('transaction_number', $transactionNumber)
            ->where('status', 'Approved')
            ->get();
        $assetRequests->each(function ($assetRequest) {
            $this->createNewAssetRequests($assetRequest);
        });
    }

    private function createNewAssetRequests($assetRequest)
    {
        if ($assetRequest->quantity > 1) {
            $newQuantity = $assetRequest->quantity_delivered;
            foreach (range(1, $newQuantity) as $index) {
                $this->addToFixedAssets($assetRequest, $assetRequest->is_addcost);
            }
        } else {
            $this->addToFixedAssets($assetRequest, $assetRequest->is_addcost, false);
        }
    }

    private function addToFixedAssets($asset, $isAddCost)
    {
        if ($isAddCost == 1) {
            $addCost = new AddCost();
            return;
        } else {
            $formula = Formula::create([
                'depreciation_method' => $asset->depreciation_method,
                'acquisition_date' => $asset->acquisition_date,
                'acquisition_cost' => $asset->acquisition_cost,
            ]);
            $fixedAsset =  $formula->fixedAsset()->create([
                'requester_id' => $asset->requester_id,
                'pr_number' => $asset->pr_number,
                'po_number' => $asset->po_number,
                'rr_number' => $asset->rr_number,
                'from_request' => 1,
                // 'vladimir_tag_number' => $asset->vladimir_tag_number,
                'asset_description' => $asset->asset_description,
                'type_of_request_id' => $asset->type_of_request_id,
                'charged_department' => $asset->department_id,
                'asset_specification' => $asset->asset_specification,
                'supplier_id' => $asset->supplier_id,
                'accountability' => $asset->accountability,
                'accountable' => $asset->accountable,
                'cellphone_number' => $asset->cellphone_number,
                'brand' => $asset->brand,
                'quantity' => $asset->quantity,
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
            ]);
            $fixedAsset->wh_number = $fixedAsset->generateWhNumber();
            $fixedAsset->vladimir_tag_number = $this->vladimirTagGeneratorRepository->vladimirTagGenerator();
            $fixedAsset->save();
        }
    }

    private function deleteAssetRequestPo($assetRequest)
    {
        $this->activityLogPo($assetRequest, $assetRequest->po_number, $assetRequest->rr_number, 0, false, true);
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        $assetRequest->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    private function handleQuantityMismatch($assetRequest)
    {
        $removedQuantity = $this->quantityRemovedHolder($assetRequest);
        $storedRemovedQuantity = $removedQuantity;

        $assetRequest->quantity = $assetRequest->quantity_delivered;
        $assetRequest->save();
        $this->activityLogPo($assetRequest, $assetRequest->po_number, $assetRequest->rr_number, $storedRemovedQuantity, true, false);
        $remaining = $this->calculateRemainingQuantity($assetRequest->transaction_number, $forTimeline = false);
        if ($remaining == 0) {
            $this->getAllitems($assetRequest->transaction_number);
        }
        return $this->responseSuccess('Remaining quantity removed successfully!');
    }
}

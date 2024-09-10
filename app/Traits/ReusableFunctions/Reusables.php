<?php

namespace App\Traits\ReusableFunctions;

use App\Models\Approvers;
use App\Models\AssetRequest;
use App\Models\FixedAsset;
use App\Models\RoleManagement;
use App\Models\User;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Http;

trait Reusables
{
    use ApiResponse;

    public function isUserFa(): bool
    {
        $user = auth('sanctum')->user()->id;
        $faRoleIds = RoleManagement::whereIn('role_name', ['Fixed Assets', 'Fixed Asset', 'Fixed Asset Associate'])->pluck('id');
        $user = User::where('id', $user)->whereIn('role_id', $faRoleIds)->exists();
        return $user ? 1 : 0;
    }

    public function isRequestApproved($uniqueNumberValue, $uniqueNumber, $model, $approvalModelName): bool
    {
        $request = $model::where([
            $uniqueNumber => $uniqueNumberValue,
            'status' => 'Approved',
        ])->exists();

        return $request ? 1 : 0;
    }

    public function requestAction($action, $uniqueNumberValue, $uniqueNumber, $model, $approvalModelName, $remarks = null)
    {
        $user = auth('sanctum')->user()->id;
        $approverId = Approvers::where('approver_id', $user)->first()->id;

        //check if the user is the approver for this request
        $nextApprover = $this->getNextApprover($approvalModelName, $uniqueNumber, $uniqueNumberValue, $approverId);
        $isApprover = $approvalModelName::where($uniqueNumber, $uniqueNumberValue)
            ->where('approver_id', $approverId)
            ->where('status', 'For Approval')
            ->first();

        if (!$this->isUserFa() && !$this->isRequestApproved($uniqueNumberValue, $uniqueNumber, $model, $approvalModelName)) {
            if (!$isApprover) {
                return $this->responseNotFound('Request not found');
            }
        }

        switch (strtolower($action)) {
            case 'approve':
                if ($this->isUserFa() && $this->isRequestApproved($uniqueNumberValue, $uniqueNumber, $model, $approvalModelName)) {
                    $this->faApproval($uniqueNumberValue, $uniqueNumber, $model);
                    break;
                }
                $this->approveRequest($uniqueNumberValue, $uniqueNumber, $model, $approvalModelName, $nextApprover->layer ?? null);
                break;
            case 'return':
                $this->returnRequest($uniqueNumberValue, $uniqueNumber, $model, $approvalModelName, $remarks);
                break;
            default:
                return $this->responseUnprocessable('Invalid Action');
        }

        $this->assetMovementLogger($model::where($uniqueNumber, $uniqueNumberValue)->first(), $action, $model, $uniqueNumber);
        return $this->responseSuccess('Request ' . $action . ' successfully');
    }

    public function approveRequest($uniqueNumberValue, $uniqueNumber, $model, $approvalModelName, $nextApprover)
    {
        // Update the status of the approval model to 'Approved'
        $approvalModelName::where($uniqueNumber, $uniqueNumberValue)->where('status', 'For Approval')->update(['status' => 'Approved']);

        // If the approval model exists
        if ($approvalModelName) {
            // If there is a next approver
            if ($nextApprover) {
                // Update the status of the model to 'For Approval of Approver {nextApprover}'
                $model::where($uniqueNumber, $uniqueNumberValue)->update(['status' => 'For Approval of Approver ' . $nextApprover]);

                // Update the status of the approval model to 'For Approval'
                $approvalModelName::where($uniqueNumber, $uniqueNumberValue)->where('layer', $nextApprover)->update(['status' => 'For Approval']);
            } else {
                // If there is no next approver, update the status of the model to 'Approved'
                $model::where($uniqueNumber, $uniqueNumberValue)->update(['status' => 'Approved']);
            }
        }
    }

    public function faApproval($uniqueNumberValue, $uniqueNumber, $model)
    {
        $fixedAssets = $model::where($uniqueNumber, $uniqueNumberValue)->get();
        $isFaApproved = $fixedAssets->where('is_fa_approved', 0)->where('status', 'Approved')->first();
        if ($isFaApproved) {

            /*$transN = $model::where($uniqueNumber, $uniqueNumberValue)->first()->transaction_number;
            $item_to_sent = $this->requestToPR($transN);
            $apiUrl = config('ymir-api.ymir_put_rr_api_url');
            $bearerToken = config('ymir-api.ymir_put_rr_api_token');

            if (is_null($apiUrl) || is_null($bearerToken)) {
                // Handle the error appropriately, e.g., log the error or throw an exception
                throw new \Exception('API URL or Bearer Token is not set.');
            }
            $item_to_sent_array = json_decode(json_encode($item_to_sent), true);

            Http::withHeaders(['Authorization' => 'Bearer ' . $bearerToken])
                ->post($apiUrl, $item_to_sent_array);*/

            $model::where($uniqueNumber, $uniqueNumberValue)->update([
                'is_fa_approved' => true,
            ]);

            //check the $model instance is not AssetRequest
            if (!($model instanceof AssetRequest)) {
                // Add to asset movement history
                $this->addToAssetMovementHistory($fixedAssets->pluck('fixed_asset_id')->toArray(), $fixedAssets[0]->created_by_id);

                // Save to FA table
                $this->saveToFaTable($uniqueNumberValue, $uniqueNumber, $model, 'transfer');
            } else {
                $model::where($uniqueNumber, $uniqueNumberValue)->update([
                    'filter' => 'Sent to Ymir', // Can be Change
                ]);


//                $model::where($uniqueNumber, $uniqueNumberValue)
//                    ->where('status', 'Approved')
//                    ->update([
//                    'pr_number' => $model::generatePrNumber(),
//                ]);
            }

        }
    }

    public function returnRequest($uniqueNumberValue, $uniqueNumber, $model, $approvalModelName, $remarks)
    {
        $approvalModelName::where($uniqueNumber, $uniqueNumberValue)
//            ->where('status', 'For Approval')
            ->update(['status' => 'Returned']);
        $model::where($uniqueNumber, $uniqueNumberValue)
            ->update([
                'status' => 'Returned',
                'remarks' => $remarks,
            ]);
    }

    public function getNextApprover($approvalModelName, $uniqueNumber, $uniqueNumberValue, $approverId)
    {
        $lastUserToApproved = $approvalModelName::where($uniqueNumber, $uniqueNumberValue)
            ->where('approver_id', $approverId)
            ->first();

        if (!$lastUserToApproved) {
            // approverId is not in the list
            // You can return null or throw an exception here
            return null;
        }

        $nextItem = $approvalModelName::where($uniqueNumber, $uniqueNumberValue)
            ->orderBy('layer')
            ->skip($lastUserToApproved->layer)
            ->take(1)
            ->first();

        return $nextItem;
    }

    public function assetMovementLogger($movementRequest, $action, $modelInstance, $uniqueNumber)
    {
        $user = auth('sanctum')->user();
        //add ed or only d to the action if it was approve or return
        $action .= strtolower($action) == 'approve' ? 'd' : (strtolower($action) == 'return' ? 'ed' : '');

        activity()
            ->causedBy($user)
            ->performedOn($modelInstance)
            ->withProperties([
                'action' => $action,
                '$uniqueNumber' => $movementRequest->$uniqueNumber,
                'remarks' => $movementRequest->remarks ?? null,
                'vladimir_tag_number' => $movementRequest->fixedAsset->vladimir_tag_number ?? null,
                'description' => $movementRequest->description,
            ])
            ->inLog(ucwords(strtolower($action)))
            ->tap(function ($activity) use ($uniqueNumber, $movementRequest) {
                $activity->subject_id = $movementRequest->$uniqueNumber;
            })
            ->log($action . ' Request');
    }

    public function addToAssetMovementHistory($assetIds, $requestorId)
    {
//        return $assetIds;
        foreach ($assetIds as $assetId) {
            $asset = FixedAsset::find($assetId);
            if ($asset) {
                $newAssetMovementHistory = $asset->replicate();
                $newAssetMovementHistory->setTable('asset_movement_histories'); // Set the table name to 'asset_movement_histories'
                $newAssetMovementHistory->fixed_asset_id = $asset->id; // Set the 'fixed_asset_id' field to the 'id' of the 'FixedAsset' model
                $newAssetMovementHistory->remarks = 'From Transfer'; // Set any additional attributes
                $newAssetMovementHistory->created_by_id = $requestorId;
                $newAssetMovementHistory->save(); // Save the new model instance to the database
            }
        }
    }

    public function saveToFaTable($uniqueNumberValue, $uniqueNumber, $model, $movementType)
    {
        switch ($movementType) {
            case 'transfer':
                $this->transfer($uniqueNumberValue, $uniqueNumber, $model, $movementType);
                break;
            case 'pullout':
//                $this->pullout();
                break;
            case 'disposal':
//                $this->desposal();
                break;
            default:
                return $this->responseUnprocessable('Invalid Action');

        }
    }

    public function transfer($uniqueNumberValue, $uniqueNumber, $model, $movementType)
    {
        $request = $model::where($uniqueNumber, $uniqueNumberValue)->get();
        $fixedAssetIds = $request->pluck('fixed_asset_id');
        foreach ($fixedAssetIds as $fixedAssetId) {
            $fixedAsset = FixedAsset::find($fixedAssetId);
            $fixedAsset->update([
                'company_id' => $request[0]->company_id,
                'business_unit_id' => $request[0]->business_unit_id,
                'department_id' => $request[0]->department_id,
                'unit_id' => $request[0]->unit_id,
                'subunit_id' => $request[0]->subunit_id,
                'location_id' => $request[0]->location_id,
                'accountability' => $request[0]->accountability,
                'accountable' => $request[0]->accountable,
                'remarks' => $request[0]->remarks,
            ]);
        }
    }
//    public function paginate($request, $data, $perPage)
//    {
//        $page = $request->input('page', 1);
//        $offset = ($page * $perPage) - $perPage;
//        return new LengthAwarePaginator(
//            array_slice($data, $offset, $perPage, true),
//            count($data),
//            $perPage,
//            $page,
//            ['path' => $request->url(), 'query' => $request->query()]
//        );
//    }


//    public function requestToPR($transactionNumber)
//    {
//
//
////        $toPr = $request->get('toPr', null);
////        $filter = $request->input('filter', 'old');
////        $transactionNumber = $request->input('transaction_number', null);
////        $perPage = $request->input('per_page', null);
////        $pagination = $request->input('pagination', null);
//        $prNumber = (new \App\Models\AssetRequest)->generatePRNumber();
//
////        $startDate = Carbon::parse($request->input('start_date'))->startOfDay();
////        $endDate = Carbon::parse($request->input('end_date'))->endOfDay();
//
//        $assetRequests = AssetRequest::where('transaction_number', $transactionNumber)
//            ->where('status', 'Approved')
//            ->where('is_fa_approved', 0)
//            ->useFilters()
//            ->orderBy('created_at', 'desc')
//            ->get()
//            ->each(function ($assetRequest) use ($prNumber) {
//                // Check if the asset request already has a PR number
//                if (is_null($assetRequest->pr_number)) {
//                    $assetRequest->update([
//                        'pr_number' => $prNumber,
//                    ]);
//                }
//            });
//
//        $filteredAndGroupedAssetRequests = $assetRequests->fresh()
//            ->where('status', 'Approved')
//            ->where('is_fa_approved', false)
////            ->whereNotNull('pr_number')
//            ->whereNull('deleted_at')
////            ->useFilters()
////            ->orderBy('created_at', 'desc')
////            ->get()
//            ->groupBy('transaction_number')
//            ->map(function ($assetRequestCollection) {
//                $latestDateNeeded = $assetRequestCollection->max('date_needed');
//                $assetRequest = $assetRequestCollection->first();
//                $assetRequest->date_needed = $latestDateNeeded;
//                $listOfItems = $assetRequestCollection->map(function ($item) {
//                    return [
////                        'item_id' => $item->id,
//                        'reference_number' => $item->pr_number,
//                        'item_code' => $item->reference_number,
//                        'item_name' => $item->asset_description,
//                        'remarks' => $item->remarks ?? null, //TODO:check
//                        'quantity' => $item->quantity,
////                        'created_at' => $item->created_at,
//                        //                        'additional_info' => $item->additional_info,
////                        'accountability' => $item->accountability,
////                        'accountable' => $item->accountable == '-' ? null : $item->accountable,
////                        'cell_number' => $item->cell_number,
////                        'brand' => $item->brand,
////                        'remarks' => $item->remarks,
//
//                        'date_needed' => $item->date_needed,
//                        'uom_id' => $item->uom->sync_id,
//                        'uom_code' => $item->uom->uom_code,
//                        'uom_name' => $item->uom->uom_name,
//                        // Add more fields as needed
//                    ];
//                })->toArray();
//                return [
//                    'vrid' => $assetRequest->requester_id, //vladimir requester ID\
//                    'pr_description' => $assetRequest->acquisition_details,
//                    'pr_number' => $assetRequest->pr_number,
//                    'transaction_number' => $assetRequest->transaction_number,
//                    "type_id" => "4",
//                    "type_name" => "Asset",
//                    'r_warehouse_id' => $assetRequest->receivingWarehouse->id,
//                    'r_warehouse_name' => $assetRequest->receivingWarehouse->warehouse_name,
//
//                    'company_id' => $assetRequest->company->sync_id,
////                    'company_code' => $assetRequest->company->company_code,
//                    'company_name' => $assetRequest->company->company_name,
//
//                    'business_unit_id' => $assetRequest->businessUnit->sync_id,
////                    'business_unit_code' => $assetRequest->businessUnit->business_unit_code,
//                    'business_unit_name' => $assetRequest->businessUnit->business_unit_name,
//
//                    'department_id' => $assetRequest->department->sync_id,
////                    'department_code' => $assetRequest->department->department_code,
//                    'department_name' => $assetRequest->department->department_name,
//
//                    'department_unit_id' => $assetRequest->unit->sync_id,
////                    'department_unit_code' => $assetRequest->unit->unit_code,
//                    'department_unit_name' => $assetRequest->unit->unit_name,
//
//                    'sub_unit_id' => $assetRequest->subunit->sync_id,
////                    'subunit_code' => $assetRequest->subunit->sub_unit_code,
//                    'sub_unit_name' => $assetRequest->subunit->sub_unit_name,
//
//                    'location_id' => $assetRequest->location->sync_id,
////                    'location_code' => $assetRequest->location->location_code,
//                    'location_name' => $assetRequest->location->location_name,
//
//                    'account_title_id' => $assetRequest->accountTitle->sync_id,
////                    'account_title_code' => $assetRequest->accountTitle->account_title_code,
//                    'account_title_name' => $assetRequest->accountTitle->account_title_name,
//                    'description' => $assetRequest->acquisition_details,
//                    'created_at' => $assetRequest->created_at,
//                    'date_needed' => $assetRequest->date_needed,
//                    'module_name' => 'Asset',
//                    'sgp' => null,
//                    'f1' => null,
//                    'f2' => null,
//                    'order' => $listOfItems
//                ];
//            })
//            ->values();
//
//
////        if ($perPage !== null && $pagination == null) {
////            $page = $request->input('page', 1);
////            $offset = $page * $perPage - $perPage;
////            $filteredAndGroupedAssetRequests = new LengthAwarePaginator($filteredAndGroupedAssetRequests->slice($offset, $perPage)->values(), $filteredAndGroupedAssetRequests->count(), $perPage, $page, [
////                'path' => $request->url(),
////                'query' => $request->query(),
////            ]);
////        }
//
//        return $filteredAndGroupedAssetRequests;
//    }
}

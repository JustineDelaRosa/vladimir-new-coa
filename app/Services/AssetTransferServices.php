<?php

namespace App\Services;

use App\Models\Approvers;
use App\Models\AssetTransferApprover;
use App\Models\AssetTransferRequest;
use App\Models\BusinessUnit;
use App\Models\Location;
use App\Models\MovementApproval;
use App\Models\MovementNumber;
use App\Models\RoleManagement;
use App\Models\Transfer;
use App\Models\TransferApproval;
use App\Models\User;
use App\Traits\AssetMovement\AssetTransferContainerHandler;
use App\Traits\AssetMovement\TransferRequestHandler;
use App\Traits\AssetMovementHandler;
use App\Traits\ReusableFunctions\Reusables;
use Essa\APIToolKit\Api\ApiResponse;
use GuzzleHttp\Psr7\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AssetTransferServices
{
    use ApiResponse, AssetTransferContainerHandler, Reusables, AssetMovementHandler;
    public function getTransfers($request, $relation)
    {
        $request->validate([
            'for_monitoring' => 'boolean',
            'status' => 'string|in:For Approval,Approved',
        ]);

        $forMonitoring = $request->for_monitoring ?? false;
        $requesterId = auth('sanctum')->user()->id;
        $role = Cache::remember("user_role_$requesterId", 60, function () use ($requesterId) {
            return User::find($requesterId)->roleManagement->role_name;
        });
        // $role = User::find($requesterId)->roleManagement->role_name;
        $adminRoles = ['Super Admin', 'Admin', 'ERP'];
        $perPage = $request->input('per_page', null);
        $status = $request->input('status', 'active');

        if (!in_array($role, $adminRoles)) {
            $forMonitoring = false;
        }

        $transferApprovalQuery = MovementNumber::query()->with($relation);

//        if (!$forMonitoring) {
//            $transferApprovalQuery->where('approver_id', $approverId);
//        }
        if (!$forMonitoring) {
            $transferApprovalQuery->where('requester_id', $requesterId);
        }
//        $transferNumbers = [];
//        if ($this->isUserFa()) {
//            $transferNumbers = MovementNumber::where('status', 'Approved')
//                ->when($status == 'Approved', function ($query) {
//                    return $query->where('is_fa_approved', true);
//                }, function ($query) {
//                    return $query->where('is_fa_approved', false);
//                })
//                ->pluck('id');
////                ->toArray();
//        }
//
//        $transferApproval = $transferApprovalQuery->where('status', $status)->get();
//        $transferNumbers = is_array($transferNumbers) ? $transferNumbers : [$transferNumbers];
//        $transferNumbers = array_merge($transferNumbers, $transferApproval->pluck('movement_number_id')->toArray());
//        $transferNumbers = Arr::flatten($transferNumbers);

        $data = $transferApprovalQuery->get();
        $data = $data->filter(function ($item) use ($relation) {
            return $item->$relation->isNotEmpty();
        })->values();

        // transform the data to the desired format
        $data = $this->transformData($data, $perPage, $request);
        if ($perPage !== null) {
            $dataArray = $data->toArray(); // Convert the collection to an array
            $page = $request->input('page', 1);
            $offset = ($page * $perPage) - $perPage;
            return new LengthAwarePaginator(
                array_slice($dataArray, $offset, $perPage, true),
                count($dataArray),
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        }
        return $data;
    }


    public function storeTransfer($request)
    {
        try {
            DB::beginTransaction();
            $fixedAssetIds = $request->assets;
            $attachments = $request->file('attachments');
            $user = auth('sanctum')->user();
            $userSubUnit = $user->subunit_id;
            $userLocation = Location::where('id', $user->location_id)->first();
            $businessUnit = BusinessUnit::where('id', $user->business_unit_id)->first();

//            if ($businessUnit->business_unit_name === 'Fresh Options') {
//                return $businessUnit->business_unit_name;
//            }
//
//            if ($userLocation->location_name === 'Head Office') {
//                return $businessUnit->business_unit_name;
//            }

            $transferApproval = AssetTransferApprover::where('subunit_id', $userSubUnit)
                ->orderBy('layer', 'asc')
                ->get();
            if ($transferApproval->isEmpty()) {
                return $this->responseUnprocessable('No approver found for you, please contact support');
            }

            $movementNumber = (new MovementNumber())->createMovementNumber(new Transfer(), $userSubUnit, $this->getApprovers(new Transfer(), $userSubUnit)); //$request->subunit_id

            foreach ($fixedAssetIds as $index => $fixedAssetId) {
                $transferRequest = Transfer::create([
                    'movement_id' => $movementNumber->id,
                    'receiver_id' => $request->receiver_id,
                    'description' => $request->description,
                    'fixed_asset_id' => $fixedAssetId['fixed_asset_id'],
                    'accountability' => $request->accountability,
                    'accountable' => $request->accountable,
                    'company_id' => $request->company_id,
                    'business_unit_id' => $request->business_unit_id,
                    'department_id' => $request->department_id,
                    'unit_id' => $request->unit_id,
                    'subunit_id' => $request->subunit_id,
                    'location_id' => $request->location_id,
                    'account_id' => $request->account_id,
                    'remarks' => $request->remarks,
                ]);

                // If this is the first iteration, add the attachments
                if ($index === 0 && $attachments) {
                    foreach ($attachments as $attachment) {
                        $attachments = is_array($attachment) ? $attachment : [$attachment];
                        $transferRequest->addMedia($attachment)->toMediaCollection('attachments');
//                        $transferRequest->addMedia($attachment)->toMediaCollection('attachments');
//                    $transferRequest->addMediaFromRequest($attachment)->toMediaCollection('attachments');
                    }
                }
            }

            DB::commit();
            return $this->responseSuccess('Asset Transfer Request Created');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->responseServerError($e);
        }
    }


    private function getApprovers($modelType, $subUnitId)
    {
        if ($modelType instanceof Transfer) {
            $requestApproversCollection = AssetTransferApprover::where('subunit_id', $subUnitId)
                ->orderBy('layer', 'asc')
                ->get();
            return $requestApproversCollection->map(function ($item) {
                return [
                    'id' => $item->approver->id,
                    'approver_id' => $item->approver->approver_id,
//                     'username' => $item->approver->user->username,
                    'layer' => $item->layer,
                    'max_layer' => $item->max('layer'),
                ];
            })->toArray();
        }


        // Add more conditions for other model types if needed
        return collect();
    }
}
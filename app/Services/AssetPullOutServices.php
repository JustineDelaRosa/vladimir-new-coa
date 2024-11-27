<?php

namespace App\Services;

use App\Models\AssetPullOutApprover;
use App\Models\MovementApproval;
use App\Models\MovementNumber;
use App\Models\PullOut;
use App\Models\User;
use App\Traits\AssetMovementHandler;
use App\Traits\PullOutHandler;
use App\Traits\ReusableFunctions\Reusables;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AssetPullOutServices
{
    use ApiResponse, AssetMovementHandler, Reusables, PullOutHandler;

//    public function __construct()
//    {
//        //
//    }

    public function getPullouts($request, $relation)
    {
        $request->validate([
            'for_monitoring' => 'boolean',
            'status' => 'string|in:For Approval,Approved',
            'is_receiver' => 'boolean',
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

        $transferApprovalQuery = MovementNumber::query();

        if ($request->is_receiver) {
            $transferApprovalQuery->with('pullout', function ($query) use ($requesterId) {
                $query->where('receiver_id', $requesterId);
            });
//            $transferApprovalQuery->whereHas('transfer', function ($query) use ($requesterId) {
//                $query->where('receiver_id', $requesterId);
//            });
            //modify the quantity of the transfer


        } elseif (!$forMonitoring) {
            $transferApprovalQuery->where('requester_id', $requesterId);
        }

        $data = $transferApprovalQuery->get();
        $data = $data->filter(function ($item) use ($relation) {
            return $item->$relation->isNotEmpty();
        })->values();

        // transform the data to the desired format
        $data = $this->pullOutTable($data, $request, $relation);

        if ($perPage !== null) {
            $dataArray = $data->toArray(); // Convert the collection to an array
            $page = $request->input('page', 1);
            $offset = ($page * $perPage) - $perPage;
            $paginatedData = array_slice($dataArray, $offset, $perPage, true);
            $paginatedData = array_values($paginatedData); // Reindex the array

            return new LengthAwarePaginator(
                $paginatedData,
                count($dataArray),
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        }
        return $data;
    }

    public function storePullOut($request)
    {
        try {
            DB::beginTransaction();
            $fixedAssetIds = $request->assets;
//            $attachments = $request->file('attachment');
            $user = auth('sanctum')->user();
            $userBusinessUnit = $user->business_unit_id;
            $userSubunit = $user->subunit_id;
            $userLocation = $user->location->location_name;

            $pulloutApproval = AssetPullOutApprover::when($userBusinessUnit == "Fresh Options", function ($query) use ($userBusinessUnit, $userSubunit, $userLocation) {
                return $query->when($userLocation !== "Head Office", function ($query) use ($userLocation) {
                    return $query->where('subunit_id', 93);
                });
            }, function ($query) use ($userSubunit) {
                return $query->where('subunit_id', $userSubunit);
            })->orderBy('layer', 'asc')->get();
            if ($pulloutApproval->isEmpty()) {
                return $this->responseBadRequest('No approver found for this subunit');
            }

            $movementNumber = (new MovementNumber())->createMovementNumber(new PullOut(), $userSubunit, $this->getApprovers(new PullOut(), $userSubunit));

            foreach ($fixedAssetIds as $index => $fixedAssetId) {

                $pullout = PullOut::create([
                    'movement_id' => $movementNumber->id,
                    'fixed_asset_id' => $fixedAssetId['fixed_asset_id'],
//                    'receiver_id' => $user->id,
                    'care_of' => $request->care_of,
                    'description' => $request->description,
                    'remarks' => $request->remarks ?? null,
//                    'status' => 'For Approval of Approver 1',
                ]);


//                if ($index === 0 && $attachments) {
//                    foreach ($attachments as $attachment) {
//                        $attachments = is_array($attachment) ? $attachment : [$attachment];
//                        $movementNumber->addMedia($attachment)->toMediaCollection('attachments');
//                    }
//                }
            }

            $this->movementLogs($movementNumber->id, 'Requested');
            DB::commit();
            return $this->responseCreated('PullOut request successfully created');

        } catch (\Exception $e) {
            DB::rollBack();
//            return $this->responseUnprocessable($e->getLine());
            return $this->responseUnprocessable($e->getMessage());
        }
    }

    public function showPullOut($movementId, $request)
    {
        $pulloutMovement = MovementNumber::withTrashed()
            ->where('id', $movementId)
            ->orderByDesc('created_at')
            ->get();

        $pullout = $pulloutMovement->filter(function ($item) {
            return $item->pullout->isNotEmpty();
        })->values();

        return $this->pullOutData($pullout);
    }

    public function getNextPullOutRequest($request, $approverId)
    {
        $status = $request->input('status', 'For Approval');

        $isUserFa = $this->isUserFa();

        $movementApprovalQuery = MovementApproval::where('approver_id', $approverId)
            ->whereHas('movementNumber', function ($query) {
                $query->whereHas('pullout');
            });

        $movementNumbers = [];

        if ($isUserFa) {
            $movementNumbers = MovementNumber::where('status', 'Approved')
                ->where('is_fa_approved', false)
                ->pluck('id')
                ->toArray();
        }

        $movementApprovals = $movementApprovalQuery->where('status', $status)->get();
        $movementNumbers = array_merge($movementNumbers, $movementApprovals->pluck('movement_number_id')->toArray());
        $movementNumbers = Arr::flatten($movementNumbers);

        $pullout = MovementNumber::where('id', $movementNumbers)->first();
        if (!$pullout) {
            return $this->responseNotFound('No pullout request found');
        }
        return $this->nextPullOutData($pullout);
    }

    public function updatePullOut($request, $movementId)
    {

        try {
            DB::beginTransaction();
            $newFixedAssetIds = collect($request->assets)->pluck('fixed_asset_id')->toArray();
            $user = auth('sanctum')->user();
            $userSubunit = $user->subunit_id;

            $pullOutApproval = AssetPullOutApprover::where('subunit_id', $userSubunit)
                ->orderBy('layer', 'asc')
                ->get();

            if ($pullOutApproval->isEmpty()) {
                return $this->responseBadRequest('No approver found for this subunit');
            }

            $movementNumber = MovementNumber::with('pullout')->where('id', $movementId)
                ->where('is_fa_approved', false)
                ->first();
            if (!$movementNumber) {
                return $this->responseNotFound('Movement Number not found');
            }

            $movementNumber->update([
                'status' => 'For Approval of Approver 1',
            ]);
            $existingFixedAssets = $movementNumber->pullout->pluck('fixed_asset_id')->toArray();

            return $newFixedAssetIds;

            $fixedAssetToAdd = array_diff($newFixedAssetIds, $existingFixedAssets);
            $fixedAssetToRemove = array_diff($existingFixedAssets, $newFixedAssetIds);

            if (!empty($fixedAssetToRemove)) {
                PullOut::where('movement_id', $movementId)
                    ->whereIn('fixed_asset_id', $fixedAssetToRemove)
                    ->delete();
            }

            foreach ($fixedAssetToAdd as $fixedAssetId) {
                PullOut::create([
                    'movement_id' => $movementId,
                    'fixed_asset_id' => $fixedAssetId,
                    'care_of' => $request->care_of,
                    'description' => $request->description,
                    'remarks' => $request->remarks ?? null,
                ]);
            }

            foreach ($newFixedAssetIds as $fixedAssetId) {
                PullOut::where('movement_id', $movementId)
                    ->where('fixed_asset_id', $fixedAssetId)
                    ->update([
                        'care_of' => $request->care_of,
                        'description' => $request->description,
                        'remarks' => $request->remarks ?? null,
                    ]);
            }

            $movementNumber->movementApproval()->update([
                'status' => null,
            ]);

            $movementNumber->movementApproval()->where('layer', 1)->update([
                'status' => 'For Approval',
            ]);

            $this->movementLogs($movementNumber->id, 'Updated');

            DB::commit();
            return $this->responseSuccess('Asset PullOut Request Updated');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->responseUnprocessable($e->getMessage());
        }
    }

    public function voidPullOut($movementId)
    {
        try {
            DB::beginTransaction();
            $user = auth('sanctum')->user();
            $movementNumber = MovementNumber::where('id', $movementId)
                ->where('requester_id', $user->id)
                ->where('is_fa_approved', false)
                ->first();
            if (!$movementNumber) {
                return $this->responseUnprocessable('Invalid Movement Number');
            }

            $movementNumber->update(['status' => 'Voided']);

            $movementNumber->movementApproval()->update(['status' => 'Voided']);

//            PullOut::where('movement_id', $movementId)->delete();

            $movementNumber->pullOut()->delete();
            $movementNumber->delete();

            $this->movementLogs($movementId, 'Voided');

            DB::commit();
            return $this->responseSuccess('Successfully Voided');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->responseServerError($e->getMessage());
        }
    }
}

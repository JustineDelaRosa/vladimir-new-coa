<?php

namespace App\Services;

use App\Models\Approvers;
use App\Models\AssetRequest;
use App\Models\AssetTransferApprover;
use App\Models\AssetTransferRequest;
use App\Models\BusinessUnit;
use App\Models\FixedAsset;
use App\Models\Location;
use App\Models\MovementApproval;
use App\Models\MovementNumber;
use App\Models\RoleManagement;
use App\Models\Transfer;
use App\Models\TransferApproval;
use App\Models\User;
use App\Traits\AssetMovement\AssetTransferContainerHandler;
use App\Traits\AssetMovement\TransferHandler;
use App\Traits\AssetMovement\TransferRequestHandler;
use App\Traits\AssetMovementHandler;
use App\Traits\AssetReleaseHandler;
use App\Traits\ReusableFunctions\Reusables;
use Essa\APIToolKit\Api\ApiResponse;
use GuzzleHttp\Psr7\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AssetTransferServices
{
    use ApiResponse, Reusables, AssetMovementHandler, TransferHandler;

    public function getTransfers($request, $relation)
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
            $transferApprovalQuery->with('transfer', function ($query) use ($requesterId) {
                $query->where('receiver_id', $requesterId);
            });
//            $transferApprovalQuery->whereHas('transfer', function ($query) use ($requesterId) {
//                $query->where('receiver_id', $requesterId);
//            });
            //modify the quantity of the transfer


        } elseif (!$forMonitoring) {
            $transferApprovalQuery->where('requester_id', $requesterId);
        }

        $data = $transferApprovalQuery->orderByDesc('created_at')->get();
        $data = $data->filter(function ($item) use ($relation) {
            return $item->$relation->isNotEmpty();
        })->values();

        // transform the data to the desired format
        $data = $this->transformData($data, $request, $relation);

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


    public function storeTransfer($request)
    {
        try {
            DB::beginTransaction();
            $fixedAssetIds = $request->assets;
            $attachments = $request->file('attachments');
            $user = auth('sanctum')->user();
            $userSubUnit = $user->subunit_id;
//            $userLocation = Location::where('id', $user->location_id)->first();
//            $businessUnit = BusinessUnit::where('id', $user->business_unit_id)->first();

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
//                    'account_id' => $request->account_id,
                    'remarks' => $request->remarks,
                ]);

                // If this is the first iteration, add the attachments
                if ($index === 0 && $attachments) {
                    foreach ($attachments as $attachment) {
                        $attachments = is_array($attachment) ? $attachment : [$attachment];
                        $movementNumber->addMedia($attachment)->toMediaCollection('attachments');
//                        $transferRequest->addMedia($attachment)->toMediaCollection('attachments');
//                    $transferRequest->addMediaFromRequest($attachment)->toMediaCollection('attachments');
                    }
                }
            }
            $this->movementLogs($movementNumber->id, 'Requested');

            DB::commit();
            return $this->responseSuccess('Asset Transfer Request Created');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->responseServerError($e->getMessage());
        }
    }

    public function showTransfer($movementId, $request)
    {

        $is_receiver = $request->is_receiver;

        $transferMovement = MovementNumber::withTrashed()
            ->when($is_receiver, function ($query) {
                $query->with('transfer', (function ($query) {
                    $query->where('receiver_id', auth('sanctum')->user()->id);
                }));
            })
            ->where('id', $movementId)
            ->orderByDesc('created_at')
            ->get();

        /*if ($is_receiver) {
            // Check if the user is receiver, requestor, or has an admin role
            $user = auth('sanctum')->user();
            $role = Cache::remember("user_role_$user->id", 60, function () use ($user) {
                return $user->role->role_name;
            });
            $adminRoles = ['Super Admin', 'Admin', 'ERP'];

            $transferMovement = $transferMovement->filter(function ($item) use ($user, $role, $adminRoles) {
                return $item->transfer->where('receiver_id', $user->id)->isNotEmpty() ||
                    $item->transfer->where('requester_id', $user->id)->isNotEmpty() ||
                    in_array($role, $adminRoles);
            })->values();
        }*/

        $transfer = $transferMovement->filter(function ($item) {
            return $item->transfer->isNotEmpty();
        })->values();

        return $this->transferData($transfer);
//        return $transfer;
    }

    public function getNextTransferRequest($request, $approverId)
    {
        $status = $request->input('status', 'For Approval');

        $isUserFa = $this->isUserFa();

        $movementApprovalsQuery = MovementApproval::where('approver_id', $approverId)
            ->whereHas('movementNumber', function ($query) {
                $query->whereHas('transfer');
            });

        $movementNumbers = []; // Initialize the variable

        if ($isUserFa) {
            // Check all the id that is approved but not yet fa approved
            $movementNumbers = MovementNumber::where('status', 'Approved')
                ->where('is_fa_approved', false)
                ->pluck('id')
                ->toArray(); // Ensure it is an array
        }

        $movementApprovals = $movementApprovalsQuery->where('status', $status)->get();
        $movementNumbers = array_merge($movementNumbers, $movementApprovals->pluck('movement_number_id')->toArray());
        $movementNumbers = Arr::flatten($movementNumbers);

        $transfer = MovementNumber::where('id', $movementNumbers)->first();
        if (!$transfer) {
            return $this->responseUnprocessable('No more transfer request');
        }
        return $this->nextTransferData($transfer);
    }


    public function updateTransfer($request, $movementId)
    {
        try {
            DB::beginTransaction();
            $newFixedAssetIds = collect($request->assets)->pluck('fixed_asset_id')->toArray();
            $attachments = $request->file('attachments');
            $user = auth('sanctum')->user();
            $userSubUnit = $user->subunit_id;

            $payload = [
                'asset_ids' => $newFixedAssetIds,
                'receiver_id' => $request->receiver_id,
                'description' => $request->description,
                'accountability' => $request->accountability,
                'accountable' => $request->accountable,
                'company_id' => $request->company_id,
                'business_unit_id' => $request->business_unit_id,
                'department_id' => $request->department_id,
                'unit_id' => $request->unit_id,
                'subunit_id' => $request->subunit_id,
                'location_id' => $request->location_id,
                'remarks' => $request->remarks,
            ];

            $toCompareTo = Transfer::where('movement_id', $movementId)->first();

//            $existingAttachments = $toCompareTo->movementNumber->getMedia('attachments')->pluck('file_name')->toArray();
            $newAttachments = $attachments ? array_map(function ($attachment) {
                return $attachment->getClientOriginalName();
            }, $attachments) : [];

            $sortedExistingAssets = Transfer::where('movement_id', $movementId)->pluck('fixed_asset_id')->toArray();
            sort($sortedExistingAssets);
            $sortedNewAssets = $payload['asset_ids'];
            sort($sortedNewAssets);

            if ($toCompareTo->receiver_id == $payload['receiver_id'] &&
                $toCompareTo->description == $payload['description'] &&
                $toCompareTo->accountability == $payload['accountability'] &&
                $toCompareTo->accountable == $payload['accountable'] &&
                $toCompareTo->company_id == $payload['company_id'] &&
                $toCompareTo->business_unit_id == $payload['business_unit_id'] &&
                $toCompareTo->department_id == $payload['department_id'] &&
                $toCompareTo->unit_id == $payload['unit_id'] &&
                $toCompareTo->subunit_id == $payload['subunit_id'] &&
                $toCompareTo->location_id == $payload['location_id'] &&
                $toCompareTo->remarks == $payload['remarks'] &&
                $sortedExistingAssets == $sortedNewAssets &&
                $newAttachments == []
            ) {
                return $this->responseSuccess('No changes made');
            }

            $transferApproval = AssetTransferApprover::where('subunit_id', $userSubUnit)
                ->orderBy('layer', 'asc')
                ->get();
            if ($transferApproval->isEmpty()) {
                return $this->responseUnprocessable('No approver found for you, please contact support');
            }

            $movementNumber = MovementNumber::where('id', $movementId)
                ->where('is_fa_approved', false)
                ->first();
            if (!$movementNumber) {
                return $this->responseNotFound('Movement Number not found');
            }

            $movementNumber->update([
                'status' => 'For Approval of Approver 1',
            ]);

            // Get existing fixed assets
            $existingFixedAssets = $movementNumber->transfer->pluck('fixed_asset_id')->toArray();

            // Determine fixed assets to add and remove
            $fixedAssetsToAdd = array_diff($newFixedAssetIds, $existingFixedAssets);
            $fixedAssetsToRemove = array_diff($existingFixedAssets, $newFixedAssetIds);

            // Remove fixed assets
            if (!empty($fixedAssetsToRemove)) {
                Transfer::where('movement_id', $movementNumber->id)
                    ->whereIn('fixed_asset_id', $fixedAssetsToRemove)
                    ->delete();
            }

            // Add new fixed assets
            foreach ($fixedAssetsToAdd as $fixedAssetId) {
                Transfer::create([
                    'movement_id' => $movementNumber->id,
                    'receiver_id' => $request->receiver_id,
                    'description' => $request->description,
                    'fixed_asset_id' => $fixedAssetId,
                    'accountability' => $request->accountability,
                    'accountable' => $request->accountable,
                    'company_id' => $request->company_id,
                    'business_unit_id' => $request->business_unit_id,
                    'department_id' => $request->department_id,
                    'unit_id' => $request->unit_id,
                    'subunit_id' => $request->subunit_id,
                    'location_id' => $request->location_id,
                    'remarks' => $request->remarks,
                ]);
            }
            

            //remove the existing attachments


            // Add attachments to the movement number
            if ($attachments) {
                $movementNumber->clearMediaCollection('attachments');
                foreach ($attachments as $attachment) {
                    $movementNumber->addMedia($attachment)->toMediaCollection('attachments');
                }
            }


            // Update existing fixed assets
            foreach ($newFixedAssetIds as $fixedAssetId) {
                Transfer::where('movement_id', $movementId)
                    ->where('fixed_asset_id', $fixedAssetId)
                    ->update([
                        'receiver_id' => $request->receiver_id,
                        'description' => $request->description,
                        'accountability' => $request->accountability,
                        'accountable' => $request->accountable,
                        'company_id' => $request->company_id,
                        'business_unit_id' => $request->business_unit_id,
                        'department_id' => $request->department_id,
                        'unit_id' => $request->unit_id,
                        'subunit_id' => $request->subunit_id,
                        'location_id' => $request->location_id,
                        'remarks' => $request->remarks,
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
            return $this->responseSuccess('Asset Transfer Request Updated');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->responseServerError($e->getMessage());
        }
    }


    public function voidTransfer($movementId)
    {
        try {
            DB::beginTransaction();
            // check if the user is the requestor
            $user = auth('sanctum')->user();
            $movementNumber = MovementNumber::where('id', $movementId)
                ->where('requester_id', $user->id)
                ->where('is_fa_approved', false)
                ->first();
            if (!$movementNumber) {
                return $this->responseNotFound('Data not found');
            }

            $movementNumber->update([
                'status' => 'Voided',
            ]);

//            $movementNumber->transfer()->update([
//                'status' => 'Voided',
//            ]);

            $movementNumber->movementApproval()->update([
                'status' => 'Voided',
            ]);
            //delete the transfer request
//            Transfer::where('movement_id', $movementNumber->id)->delete();
            $movementNumber->transfer()->delete();
            //delete the movement number
            $movementNumber->delete();

            $this->movementLogs($movementNumber->id, 'Voided');

            DB::commit();
            return $this->responseSuccess('Successfully Voided');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->responseServerError($e->getMessage());
        }
    }

    public function transferReceiverView($request)
    {
        $user = auth('sanctum')->user();
        $userId = $user->id;
        $status = $request->status;

        $transfer = Transfer::where('receiver_id', $userId)
            ->wherehas('movementNumber', function ($query) use ($status) {
                $query->where('is_fa_approved', true);
            })
            ->when($status == 'To Receive', function ($query) {
                return $query->whereNull('received_at');
            }, function ($query) {
                return $query->whereNotNull('received_at');
            })->get();

        $transfer = $this->receiverTableViewing($transfer);

        // add pagination
        $perPage = $request->input('per_page', null);
        if ($perPage !== null) {
            $dataArray = $transfer->toArray(); // Convert the collection to an array
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
        return $transfer;
    }


    public function transferConfirmation($request)
    {
        $transferIds = $request->transfer_ids;
//        $isReceived = $request->is_received;
        $userId = auth('sanctum')->user()->id;

        try {
            DB::beginTransaction();
            foreach ($transferIds as $transferId) {
                $transfer = Transfer::where('id', $transferId)->where('receiver_id', $userId)->first();
                if (!$transfer) {
                    return $this->responseNotFound('Transfer not found');
                }

                $transfer->update([
                    'received_at' => now(),
                ]);

                //check if this is the last one to be received then update the movement number status
                $transferCount = Transfer::where('movement_id', $transfer->movement_id)
                    ->where('received_at', null)
                    ->count();
                if ($transferCount === 0) {
                    $movementNumber = MovementNumber::where('id', $transfer->movement_id)->first();
                    if (!$movementNumber) {
                        return $this->responseNotFound('Movement Number not found');
                    }
                    $movementNumber->update([
                        'is_received' => true,
                    ]);

                    $movementNumber->transfer()->update([
                        'received_at' => now(),
                    ]);

                    $movementNumber->update([
                        'status' => 'Received',
                    ]);

                    $this->movementLogs($movementNumber->id, 'fully Received');
                }


                $this->movementLogs($transfer->movement_id, 'item Received');
                $this->updateAssetData($transferId);
                $this->addToMovementHistory($transferId);
            }

            DB::commit();
            return $this->responseSuccess('Asset Transfer Request Confirmed');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->responseServerError($e->getMessage());
        }
    }

    public function addToMovementHistory($transferId)
    {
        $transfer = Transfer::where('id', $transferId)->first();
        $fixedAsset = FixedAsset::where('id', $transfer->fixed_asset_id)->first();

        if ($fixedAsset) {
            $newMovementHistory = $fixedAsset->replicate();
            $newMovementHistory->setTable('asset_movement_histories');
            $newMovementHistory->fixed_asset_id = $fixedAsset->id;
            $newMovementHistory->remarks = 'From Transfer';
            $newMovementHistory->created_by_id = $transfer->movementNumber->requester_id;
            $newMovementHistory->save();
        }
    }

    public function updateAssetData($transferId)
    {
        $transfer = Transfer::where('id', $transferId)->first();
        $assetId = $transfer->fixed_asset_id;
        $fixedAsset = FixedAsset::find($assetId);
        $fixedAsset->update([
            'company_id' => $transfer->company_id,
            'business_unit_id' => $transfer->business_unit_id,
            'department_id' => $transfer->department_id,
            'unit_id' => $transfer->unit_id,
            'subunit_id' => $transfer->subunit_id,
            'location_id' => $transfer->location_id,
            'accountability' => $transfer->accountability,
            'accountable' => $transfer->accountable,
            'remarks' => $transfer->remarks,
        ]);
    }


}
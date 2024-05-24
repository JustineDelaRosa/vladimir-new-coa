<?php

namespace App\Traits\AssetMovement;

use App\Models\Approvers;
use App\Models\AssetMovementHistory;
use App\Models\AssetTransferApprover;
use App\Models\AssetTransferRequest;
use App\Models\FixedAsset;
use App\Models\RoleManagement;
use App\Models\TransferApproval;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

trait TransferRequestHandler
{
    public function setTransferApprovals($items, $createdById, $assetTransfer, $subunitApproverModel, $approvalModel)
    {
        $subunitId = $items[0]->subunit_id ?? $items ?? null;

        $transferUnitApprovals = $subunitApproverModel::where('subunit_id', $subunitId)
            ->orderBy('layer', 'asc')
            ->get();
        $layerIds = $transferUnitApprovals->map(function ($item) {
            return $item->approver->approver_id;
        })->toArray();
        $isRequesterApprover = in_array($createdById, $layerIds);
        $requesterLayer = array_search($createdById, $layerIds) + 1;
//        return 'okay';
        foreach ($transferUnitApprovals as $approval) {
            $approverId = $approval->approver_id;
            $layer = $approval->layer;

            $status = null;

            if ($isRequesterApprover) {
                if ($layer == $requesterLayer || $layer < $requesterLayer) {
                    $status = "Approved";
                } elseif ($layer == $requesterLayer + 1) {
                    $status = "For Approval";
                }
            } elseif ($layer == 1) { // if the requester is not an approver, only the first layer should be "For Approval"
                $status = "For Approval";
            }

            $approvalModel::create([
                'transfer_number' => $assetTransfer->transfer_number,
                'approver_id' => $approverId,
                'requester_id' => $createdById,
                'layer' => $layer,
                'status' => $status,
            ]);
        }
    }

    public function transformTransferRequest($transferRequest)
    {
        return [
            'transfer_number' => $transferRequest->transfer_number,
            'description' => $transferRequest->description,
            'quantity' => $transferRequest->quantity,
            'current_approver' => $this->getCurrentApprover($transferRequest),
            'process_count' => $this->getProcessCount($transferRequest),
            'steps' => $this->setSteps($transferRequest),
            'history' => Activity::whereSubjectType(AssetTransferRequest::class)
                ->whereSubjectId($transferRequest->transfer_number)
                ->get()
                ->map(function ($activity) {
                    return [
                        'id' => $activity->id,
                        'action' => $activity->log_name,
                        'causer' => $activity->causer,
                        'description' => $activity->properties['description'],
                        'vladimir_tag_number' => $activity->properties['vladimir_tag_number'],
                        'remarks' => $activity->properties['remarks'],
                        'created_at' => $activity->created_at,
                    ];
                }),
            'requester' => [
                'id' => $transferRequest->createdBy->id,
                'first_name' => $transferRequest->createdBy->firstname,
                'last_name' => $transferRequest->createdBy->lastname,
                'employee_id' => $transferRequest->createdBy->employee_id,
                'username' => $transferRequest->createdBy->username,
            ],
            'status' => $transferRequest->is_fa_approved ? 'Approved' : $transferRequest->status,
            'company' => [
                'id' => $transferRequest->company->id ?? '-',
                'company_code' => $transferRequest->company->company_code ?? '-',
                'company_name' => $transferRequest->company->company_name ?? '-',
            ],
            'business_unit' => [
                'id' => $transferRequest->businessUnit->id ?? '-',
                'business_unit_code' => $transferRequest->businessUnit->business_unit_code ?? '-',
                'business_unit_name' => $transferRequest->businessUnit->business_unit_name ?? '-',
            ],
            'department' => [
                'id' => $transferRequest->department->id ?? '-',
                'department_code' => $transferRequest->department->department_code ?? '-',
                'department_name' => $transferRequest->department->department_name ?? '-',
            ],
            'unit' => [
                'id' => $transferRequest->unit->id ?? '-',
                'unit_code' => $transferRequest->unit->unit_code ?? '-',
                'unit_name' => $transferRequest->unit->unit_name ?? '-',
            ],
            'subunit' => [
                'id' => $transferRequest->subunit->id ?? '-',
                'subunit_code' => $transferRequest->subunit->sub_unit_code ?? '-',
                'subunit_name' => $transferRequest->subunit->sub_unit_name ?? '-',
            ],
            'location' => [
                'id' => $transferRequest->location->id ?? '-',
                'location_code' => $transferRequest->location->location_code ?? '-',
                'location_name' => $transferRequest->location->location_name ?? '-',
            ],
            'created_at' => $transferRequest->created_at,
        ];
    }

    public function updateTransferRequest($transferRequest, $request)
    {
        $transferRequest->fill([
            'fixed_asset_id' => $request->fixed_asset_id,
            'accountable' => $request->accountable,
            'company_id' => $request->company_id,
            'business_unit_id' => $request->business_unit_id,
            'department_id' => $request->department_id,
            'unit_id' => $request->unit_id,
            'subunit_id' => $request->subunit_id,
            'location_id' => $request->location_id,
            'remarks' => $request->remarks,
            'description' => $request->description,
        ]);

        $this->updateAllAsset($transferRequest, $request);
        return $request;
    }

    public function updateAllAsset($transferRequest, $request)
    {

        $allRequest = AssetTransferRequest::where('transfer_number', $transferRequest->transfer_number)->get();
//        $tr= null;
        foreach ($allRequest as $tr) {
            $tr->fill([
                'fixed_asset_id' => $request->fixed_asset_id,
//                'accountable' => $request->accountable,
                'company_id' => $request->company_id,
                'business_unit_id' => $request->business_unit_id,
                'department_id' => $request->department_id,
                'unit_id' => $request->unit_id,
                'subunit_id' => $request->subunit_id,
                'location_id' => $request->location_id,
//                'remarks' => $request->remarks,
                'description' => $request->description,
            ]);
            $tr->save();
        }

        return;
    }

    public function handleAttachment($transferRequest, $request)
    {

        if (isset($request->attachments)) {
            $transferRequest->clearMediaCollection('attachments');
            foreach ($request->attachments as $attachment) {
                $transferRequest->addMedia($attachment)->toMediaCollection('attachments');
            }
        } else {
            $transferRequest->clearMediaCollection('attachments');
        }
    }

    public function approverChanged($id, $modelName, $approvalModelName, $subunitApprovalModelName, $uniqueNumber)
    {
        $user = auth('sanctum')->user()->id;
        $modelRequest = $modelName::find($id);
        $uniqueNumberValue = $modelRequest->$uniqueNumber;
        $subunitId = $modelRequest->subunit_id;
        $subunitApproverIds = $subunitApprovalModelName::where('subunit_id', $subunitId)->pluck('approver_id')->toArray();
        $approverIds = $approvalModelName::where('transfer_number', $uniqueNumberValue)->pluck('approver_id')->toArray();
        if (array_diff($subunitApproverIds, $approverIds)) {
            $approvalModelName::where('transfer_number', $uniqueNumberValue)->delete();
//            return $uniqueNumberValue;
            $items = $modelName::where('transfer_number', $uniqueNumberValue)->get();

            $this->setTransferApprovals($items, $user, $modelRequest, $subunitApprovalModelName, $approvalModelName);
        }
//        return 'notdiff';
        $firstLayer = $this->firstLayer($uniqueNumberValue, $approvalModelName);

        $currentApprovalLayer = $approvalModelName::where("$uniqueNumber", $uniqueNumberValue)
            ->where('status', '!=', 'Void')
            ->where('layer', $firstLayer)->first();

        if (!$currentApprovalLayer || $currentApprovalLayer->requester_id != $user) {
            return $this->responseUnprocessable('Invalid Action');
        }

        if ($modelRequest->status == 'Returned') {
            $this->updateTransferStatus($approvalModelName, $modelName, 'Returned');
        } else {
            $this->setToNull($uniqueNumber, $uniqueNumberValue, $approvalModelName);
            $this->setRequestStatus($uniqueNumber, $uniqueNumberValue, $modelName, 'For Approval of Approver ' . ($currentApprovalLayer->layer));
            $this->updateTransferStatus($approvalModelName, $modelName, 'For Approval');
        }
        return $this->responseSuccess('Request updated Successfully');
    }

    public function firstLayer($transactionNumber, $approvalModelName)
    {
        $userId = auth('sanctum')->user()->id;

        $transferApproval = $approvalModelName->where('transfer_number', $transactionNumber)
            ->where('approver_id', function ($query) use ($userId) {
                $query->select('id')
                    ->from((new Approvers)->getTable())
                    ->where('approver_id', $userId)
                    ->limit(1);
            })->first();

        return $transferApproval ? $transferApproval->layer + 1 : 1;
    }

    public function updateTransferStatus($approvalModel, $model, $status = null)
    {
        $user = auth('sanctum')->user()->id;

        $approvalModel->update(['status' => $status]);
        if ($approvalModel->first()->requester_id !== $user) {
            if ($status != 'For Approval') {
                $this->logActivity($approvalModel, $approvalModel->transfer_number, $model, $status);
            }
        }
    }

    public function setToNull($uniqueNumber, $uniqueNumberValue, $approvalModelName, $status = null)
    {
        $approvalModels = $approvalModelName::where($uniqueNumber, $uniqueNumberValue)->get();
        if ($status == 'Void') {
            $approvalModels->each->update(['status' => 'Void']);
        } else {
            $layer = $this->firstLayer($uniqueNumberValue, $approvalModelName);
            $approvalModels->where('layer', '>', $layer)->each->update(['status' => null]);
        }
    }

    public function setRequestStatus($uniqueNumber, $uniqueNumberValue, $modelName, $status, $remarks = null)
    {
        $modelName = $modelName::withTrashed()->where("$uniqueNumber", $uniqueNumberValue)->get();
        if ($status == 'Returned') {
            $modelName->each->update(['status' => $status, 'remarks' => $remarks]);
        } else {
            $modelName->each->update(['status' => $status]);
        }
    }

    public function logActivity($approvalModel, $transfer_number, $model, $status)
    {
//        return $transfer_number;
        $user = auth('sanctum')->user();
        activity()
            ->causedBy($user)
            ->performedOn($model)
            ->withProperties($this->setActivityLogProperties($status, $approvalModel, $transfer_number))
            ->tap(function ($activity) use ($transfer_number) {
                $activity->subject_id = $transfer_number;
            })
            ->inLog('Updated')
            ->log('Updated');

    }

    public function setActivityLogProperties($status, $approvalModel, $transfer_number)
    {
        return [
            'status' => $status,
            'transfer_number' => $transfer_number,
        ];
    }


    public function deleteRequestItem($id, $uniqueNumber, $model, $approvalModelName)
    {

        //check if the user is in approvers table
        $user = auth('sanctum')->user()->id;
        $approverId = Approvers::where('approver_id', $user)->first()->id;
        $uniqueNumberValue = $model::withTrashed()->where('id', $id)->first()->$uniqueNumber;
        $nextApprover = $this->getNextApprover($approvalModelName, $uniqueNumber, $uniqueNumberValue, $approverId);
        $requestItem = $model::withTrashed()->where('id', $id)->first();
        if (!$nextApprover) {
            //if status is not For Approval of approver 1 or Returned then give an error
            if ($requestItem->status !== 'For Approval of Approver 1' && $requestItem->status !== 'Returned') {
                return $this->responseUnprocessable('Item cannot be deleted');
            }
        }
        if ($nextApprover->status !== 'For Approval' && $nextApprover->status !== 'Returned') {
            return $this->responseUnprocessable('Item cannot be deleted');
        }

        //check if the item is already deleted
        if ($requestItem->deleted_at) {
            return $this->responseUnprocessable('Item already deleted');
        }

//        $requestItem->update([
//            'deleted_by' => $user,
//            'filter' => null
//        ]);
        //delete the request item
        $requestItem->delete();
        $cookie = cookie('is_changed', true);
        return $this->responseSuccess('Item successfully deleted')->withCookie($cookie);
    }


    public function deleteRequest($uniqueNumberValue, $uniqueNumber, $model, $approvalModelName)
    {

        //check if the user is in approvers table
        $user = auth('sanctum')->user()->id;
        $approverId = Approvers::where('approver_id', $user)->first()->id;
        $nextApprover = $this->getNextApprover($approvalModelName, $uniqueNumber, $uniqueNumberValue, $approverId);
        $requestItem = $model::where($uniqueNumber, $uniqueNumberValue)->get();


        if (!$nextApprover) {
            //if status is not For Approval of approver 1 or Returned then give an error
            if ($requestItem->status !== 'For Approval of Approver 1' && $requestItem->status !== 'Returned') {
                return $this->responseUnprocessable('Item cannot be deleted');
            }
        }
        if ($nextApprover->status !== 'For Approval' && $nextApprover->status !== 'Returned') {
            return $this->responseUnprocessable('Item cannot be deleted');
        }

        //check if the item is already deleted
//        if ($requestItem->deleted_at) {
//            return $this->responseUnprocessable('Item already deleted');
//        }

//        $requestItem->update([
//            'deleted_by' => $user,
//            'filter' => null
//        ]);
        //delete the request item
        $requestItem->each->delete();
        $cookie = cookie('is_changed', true);
        return $this->responseSuccess('Item successfully deleted')->withCookie($cookie);
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
            ->skip($lastUserToApproved->layer)
            ->take(1)
            ->first();

        return $nextItem;
    }

    public function dlAttachments($uniqueNumberValue, $uniqueNumber, $model): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $items = $model::withTrashed()->where($uniqueNumber, $uniqueNumberValue)->get();

        // Create a temporary zip file
        $zipFile = tempnam(sys_get_temp_dir(), 'attachments') . '.zip';
        $zip = new \ZipArchive();
        $zip->open($zipFile, \ZipArchive::CREATE);

        // Add all media files to the zip
        foreach ($items as $item) {
            $mediaItems = $item->getMedia('attachments');
            foreach ($mediaItems as $mediaItem) {
                $zip->addFile($mediaItem->getPath(), $mediaItem->file_name);
            }
        }

        $zip->close();

        // Return a response to download the zip file
        return response()->download($zipFile, $uniqueNumberValue . '-attachments.zip')
            ->deleteFileAfterSend(true);
    }

    public function isUserFa(): bool
    {
        $user = auth('sanctum')->user()->id;
        $faRoleIds = RoleManagement::whereIn('role_name', ['Fixed Assets', 'Fixed Asset Associate'])->pluck('id');
        $user = User::where('id', $user)->whereIn('role_id', $faRoleIds)->exists();
        return $user ? 1 : 0;
    }

    public function isRequestApproved($uniqueNumberValue, $uniqueNumber, $model, $approvalModelName): bool
    {
        $request = $model::where([
            $uniqueNumber => $uniqueNumberValue,
            'status' => 'Approved'
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

        $this->assetMovementLogger($model::where($uniqueNumber, $uniqueNumberValue)->first(), $action, $model);
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
            $model::where($uniqueNumber, $uniqueNumberValue)->update(['is_fa_approved' => true]);

            // Add to asset movement history
            $this->addToAssetMovementHistory($fixedAssets->pluck('fixed_asset_id')->toArray(), $fixedAssets[0]->created_by_id);

            // Save to FA table
            $this->saveToFaTable($uniqueNumberValue, $uniqueNumber, $model, 'transfer');
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

    public function getCurrentApprover($movementRequest)
    {
        $approver = $movementRequest->load('transferApproval')->transferApproval->where('status', 'For Approval')->first()->approver->user ?? null;
        if (!$approver) {
            $isFaApprove = $movementRequest->is_fa_approved;
            if (!$isFaApprove) {
                return [
                    'firstname' => 'For Approval of FA',
                    'lastname' => '',
                ];
            } else {
                return [
                    'firstname' => 'Transfer Approved',
                    'lastname' => '',
                ];
            }
        }
        return $approver;
    }

    public function getProcessCount($movementRequest)
    {
        $approver = $movementRequest->load('transferApproval')->transferApproval->where('status', 'For Approval')->first()->layer ?? null;
        if (!$approver) {
            $isFaApprove = $movementRequest->is_fa_approved;
            $allLayers = $movementRequest->load('transferApproval')->transferApproval->pluck('layer');
            if (!$isFaApprove) {
                return $allLayers->max() + 1;
            } else {
                return $allLayers->max() + 2;
            }
        }
        return $approver;

    }

    public function setSteps($movementRequest)
    {
        $approvers = $movementRequest->transferApproval;
        $approvers = $approvers->sortBy('layer');

        $steps = [];
        foreach ($approvers as $approver) {
            $steps[] = $approver->approver->user->firstname . ' ' . $approver->approver->user->lastname;
        }
        $steps[] = 'For Approval of FA';
        return $steps;
    }

    public function assetMovementLogger($movementRequest, $action, $modelIncetance)
    {
        $user = auth('sanctum')->user();
        activity()
            ->causedBy($user)
            ->performedOn($modelIncetance)
            ->withProperties([
                'action' => $action,
                'transfer_number' => $movementRequest->transfer_number,
                'remarks' => $movementRequest->remarks ?? null,
                'vladimir_tag_number' => $movementRequest->fixedAsset->vladimir_tag_number,
                'description' => $movementRequest->description,
            ])
            ->inLog('Asset Movement')
            ->tap(function ($activity) use ($movementRequest) {
                $activity->subject_id = $movementRequest->transfer_number;
            })
            ->log($action . ' Request');

    }
}

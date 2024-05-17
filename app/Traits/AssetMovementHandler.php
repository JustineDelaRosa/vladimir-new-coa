<?php

namespace App\Traits;

use App\Models\Approvers;
use App\Models\FixedAsset;
use App\Models\TransferApproval;
use Essa\APIToolKit\Api\ApiResponse;

trait AssetMovementHandler
{
    use ApiResponse;

    public function setTransferApprovals($items, $createdById, $assetTransfer, $subunitApproverModel, $approvalModel)
    {
        $transferUnitApprovals = $subunitApproverModel::where('subunit_id', $items[0]->subunit_id)
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
            $this->setRequestStatus($uniqueNumber, $uniqueNumberValue, $modelName, 'For Approval ' . ($currentApprovalLayer->layer));
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
        $uniqueNumberValue = $model::where('id', $id)->first()->$uniqueNumber;
        $nextApprover = $this->getNextApprover($approvalModelName, $uniqueNumber, $uniqueNumberValue, $approverId);
        $requestItem = $model::where('id', $id)->first();
        if (!$nextApprover) {
            //if status is not For Approval of approver 1 or Returned then give an error
            if ($requestItem->status !== 'For Approval of Approver 1' && $requestItem->status !== 'Returned') {
                return $this->responseUnprocessable('Item cannot be deleted');
            }
        }
        if ($nextApprover->status !== 'For Approval' && $nextApprover->status !== 'Returned') {
            return $this->responseUnprocessable('Item cannot be deleted');
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
        if (!$isApprover) {
            return $this->responseUnprocessable('Invalid Action');
        }


        switch (strtolower($action)) {
            case 'approve':
                $this->approveRequest($uniqueNumberValue, $uniqueNumber, $model, $approvalModelName, $nextApprover->layer ?? null);
                break;
            case 'return':
                $this->returnRequest($uniqueNumberValue, $uniqueNumber, $model, $approvalModelName, $remarks);
                break;
            default:
                return $this->responseUnprocessable('Invalid Action');
        }
        return $this->responseSuccess('Request ' . $action . ' successfully');
    }

    public function approveRequest($uniqueNumberValue, $uniqueNumber, $model, $approvalModelName, $nextApprover)
    {
        $fixedAssets = $model::where($uniqueNumber, $uniqueNumberValue)->get();
        $approvalModelName::where($uniqueNumber, $uniqueNumberValue)
            ->where('status', 'For Approval')
            ->update(['status' => 'Approved']);
        if ($approvalModelName) {
            if ($nextApprover) {
                $model::where($uniqueNumber, $uniqueNumberValue)
                    ->update(['status' => 'For Approval of Approver ' . $nextApprover]);
                $approvalModelName::where($uniqueNumber, $uniqueNumberValue)
                    ->where('layer', $nextApprover)
                    ->update(['status' => 'For Approval']);
            } else {
                $model::where($uniqueNumber, $uniqueNumberValue)
                    ->update(['status' => 'Approved']);
                $this->addToAssetMovementHistory($fixedAssets->pluck('fixed_asset_id')->toArray(), $fixedAssets[0]->created_by_id);
                $this->saveToFaTable($uniqueNumberValue, $uniqueNumber, $model, 'transfer');
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
}

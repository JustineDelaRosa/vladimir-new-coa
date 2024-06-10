<?php
namespace App\Traits\ReusableFunctions;

use App\Models\Approvers;
use App\Models\RoleManagement;
use App\Models\User;
use Essa\APIToolKit\Api\ApiResponse;

trait Reusables
{
    use ApiResponse;

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
            $model::where($uniqueNumber, $uniqueNumberValue)->update([
                'is_fa_approved' => true,
                'filter' => 'Sent to Ymir', // Can be Change
            ]);

//            // Add to asset movement history
            //            $this->addToAssetMovementHistory($fixedAssets->pluck('fixed_asset_id')->toArray(), $fixedAssets[0]->created_by_id);
            //
            //            // Save to FA table
            //            $this->saveToFaTable($uniqueNumberValue, $uniqueNumber, $model, 'transfer');
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
}

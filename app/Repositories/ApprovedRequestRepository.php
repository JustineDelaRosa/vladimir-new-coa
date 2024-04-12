<?php

namespace App\Repositories;

use App\Models\Approvers;
use App\Models\AssetApproval;
use App\Models\AssetRequest;
use App\Models\DepartmentUnitApprovers;
use App\Traits\AssetRequestHandler;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

class ApprovedRequestRepository
{
    use ApiResponse, AssetRequestHandler;

    public function approveRequest($assetApprovalId): JsonResponse
    {

        $assetApproval = $this->findAssetApproval($assetApprovalId);
        $approverId = $this->findApproverId();
        $status = "Approved";

        if ($assetApproval->status != 'For Approval') {
            return $this->responseUnprocessable('You are not allowed to do this action');
        }

        if ($this->isInvalidApprover($assetApproval->approver_id, $approverId)) {
            return $this->responseUnprocessable('You are not the approver of this request');
        }

        if ($this->isAssetApprovalWithStatus($assetApprovalId, $status)) {
            return $this->responseUnprocessable('You already approved this request');
        }

        if ($this->checkStatus($assetApprovalId)) {
            return $this->responseUnprocessable('Its not your turn for this request');
        }

        $this->updateAssetApprovalStatus($assetApproval, 'Approved');

        $nextLayerOfApprover = $this->findNextLayerApprover($assetApproval, $assetApproval->transaction_number,);

        if (empty($nextLayerOfApprover)) {
            $this->updateAssetRequestStatus($assetApproval->transaction_number, 'Approved');
        } else {
            $this->updateAssetApprovalStatus($nextLayerOfApprover, 'For Approval');
            $this->updateAssetRequestStatus($assetApproval->transaction_number, 'For Approval of Approver ' . ($assetApproval->layer + 1));
        }

        return $this->responseSuccess('Asset Request Approved Successfully');
    }

    //RETURN REQUEST
    public function returnRequest($assetApprovalId, $remarks = null): JsonResponse
    {

        $assetApproval = $this->findAssetApproval($assetApprovalId);
        $approverId = $this->findApproverId();
        $status = "Returned";
        if ($assetApproval->status != 'For Approval') {
            return $this->responseUnprocessable('You are not allowed to do this action');
        }

        if ($this->isInvalidApprover($assetApproval->approver_id, $approverId)) {
            return $this->responseUnprocessable('You are not the approver of this request');
        }
        if ($this->isAssetApprovalWithStatus($assetApprovalId, $status)) {
            return $this->responseUnprocessable('You already return this request');
        }
        if ($this->checkStatus($assetApprovalId)) {
            return $this->responseUnprocessable('Its not your turn for this request');
        }
        $this->updateAssetRequestStatus($assetApproval->transaction_number, 'Returned', $remarks);

        $this->updateAssetApprovalStatus($assetApproval, 'Returned');
//        $this->logActivity($assetApproval, 'Returned');

        return $this->responseSuccess('Asset Request Return Successfully');
    }

    //RESUBMITTING REQUEST
    public function resubmitRequest($transactionNumber)
    {
        $user = auth('sanctum')->user();
        $subUnitId = $this->getSubUnitId($transactionNumber);
        $approverIds = $this->getApproverIds($subUnitId);
        $assetApprovalIds = $this->getAssetApprovalIds($transactionNumber);
        if ($this->isApproverListChanged($approverIds, $assetApprovalIds)) {
            // Delete the previous approvers
            AssetApproval::where('transaction_number', $transactionNumber)->delete();

            // Get the items related to the transaction
            $items = AssetRequest::where('transaction_number', $transactionNumber)->get();
            $assetRequest = AssetRequest::where('transaction_number', $transactionNumber)->first();

            // Add the new approvers
            $this->createAssetApprovals($items, $user->id, $assetRequest);
        }

        $defaultLayer = $this->getUserLayer($transactionNumber);

        $assetApproval = AssetApproval::where('transaction_number', $transactionNumber)
            ->where('status', '!=', 'Void')
            ->where('layer', $defaultLayer)->first();


        if (!$assetApproval || $assetApproval->requester_id != $user->id) {
            return $this->responseUnprocessable('Invalid Action');
        }
        $this->updateToNullOrVoid($transactionNumber);
        $this->updateAssetRequestStatus($assetApproval->transaction_number, 'For Approval of Approver ' . ($assetApproval->layer));
        $this->updateAssetApprovalStatus($assetApproval, 'For Approval');
        $this->logActivity($assetApproval, 'Resubmitted');
        return $this->responseSuccess('Asset Request Resubmitted Successfully');
    }

    public function isApproverChange($transactionNumber)
    {
        $user = auth('sanctum')->user();
        $subUnitId = $this->getSubUnitId($transactionNumber);
        $approverIds = $this->getApproverIds($subUnitId);
        $assetApprovalIds = $this->getAssetApprovalIds($transactionNumber);
        if ($this->isApproverListChanged($approverIds, $assetApprovalIds)) {
            // Delete the previous approvers
            AssetApproval::where('transaction_number', $transactionNumber)->delete();

            // Get the items related to the transaction
            $items = AssetRequest::where('transaction_number', $transactionNumber)->get();
            $assetRequest = AssetRequest::where('transaction_number', $transactionNumber)->first();

            // Add the new approvers
            $this->createAssetApprovals($items, $user->id, $assetRequest);
        }

        $defaultLayer = $this->getUserLayer($transactionNumber);

        $assetApproval = AssetApproval::where('transaction_number', $transactionNumber)
            ->where('status', '!=', 'Void')
            ->where('layer', $defaultLayer)->first();
        if (!$assetApproval || $assetApproval->requester_id != $user->id) {
            return $this->responseUnprocessable('Invalid Action');
        }

        $assetRequest = AssetRequest::where('transaction_number', $transactionNumber)->first();
        if ($assetRequest->status == 'Returned') {
//            $this->updateAssetRequestStatus($assetRequest->transaction_number, 'Returned' . ($assetApproval->layer));
            $this->updateAssetApprovalStatus($assetApproval, 'Returned');
        } else {
            $this->updateToNullOrVoid($transactionNumber);
            $this->updateAssetRequestStatus($assetApproval->transaction_number, 'For Approval of Approver ' . ($assetApproval->layer));
            $this->updateAssetApprovalStatus($assetApproval, 'For Approval');
        }
        return $this->responseSuccess('Asset Request updated Successfully');
    }

    private function getSubUnitId($transactionNumber)
    {
        return AssetRequest::where('transaction_number', $transactionNumber)->first()->subunit_id;
    }

    private function getApproverIds($subUnitId)
    {
        return DepartmentUnitApprovers::where('subunit_id', $subUnitId)->get()->pluck('approver_id')->toArray();
    }

    private function getAssetApprovalIds($transactionNumber)
    {
        return AssetApproval::where('transaction_number', $transactionNumber)->get()->pluck('approver_id')->toArray();
    }

    private function isApproverListChanged($approverIds, $assetApprovalIds)
    {
        return array_diff($approverIds, $assetApprovalIds);
    }

    //VOID REQUEST
    //    public function voidRequest($assetRequestIds): JsonResponse
    //    {
    //        foreach ($assetRequestIds as $id) {
    //            $assetRequest = AssetRequest::where('id', $id)->where('status', 'Returned')->first();
    //            if (!$assetRequest) {
    //                return $this->responseUnprocessable('Invalid Action');
    //            }
    //            $this->updateAssetRequestStatus($assetRequest->transaction_number, 'Void');
    //            $this->updateToNullOrVoid($id, 'Void');
    //            activity()
    //                ->causedBy(auth('sanctum')->user())
    //                ->performedOn($assetRequest)
    //                ->withProperties(
    //                    [
    //                        'asset_request_id' => $assetRequest->id,
    //                        'requester' => [
    //                            'id' => $assetRequest->requester->id,
    //                            'firstname' => $assetRequest->requester->firstname,
    //                            'lastname' => $assetRequest->requester->lastname,
    //                            'employee_id' => $assetRequest->requester->employee_id,
    //                        ],
    //                        'status' => 'Void',
    //                    ]
    //                )
    //                ->inLog('Void')
    //                ->log('Asset Request Voided by ' . auth('sanctum')->user()->employee_id . '.');
    //        }
    //        return $this->responseSuccess('Asset Request Voided Successfully');
    //    }


    private function findAssetApproval(int $id)
    {
        return AssetApproval::find($id);
    }

    private function findApproverId()
    {
        $user = auth('sanctum')->user();
        return Approvers::where('approver_id', $user->id)->value('id');
    }

    private function isInvalidApprover($approver_id, $approverId): bool
    {
        return $approver_id != $approverId;
    }

    private function updateAssetApprovalStatus($assetApproval, string $status)
    {
        $user = auth('sanctum')->user()->id;

        $assetApproval->update(['status' => $status]);
        //check if the user is the requester
        if ($assetApproval->requester_id !== $user) {
            if ($status != 'For Approval') {
                $this->logActivity($assetApproval, $status);
            }
        }
    }

    private function findNextLayerApprover($assetApproval, $transactionNumber)
    {
        return AssetApproval::where([
            'requester_id' => $assetApproval->requester_id,
            'transaction_number' => $transactionNumber,
        ])->where('layer', $assetApproval->layer + 1)->first();
    }

    private function updateAssetRequestStatus($transactionNumber, string $status, $remarks = null)
    {
        //foreach asset request with the same transaction number update the status
        $assetRequest = AssetRequest::withTrashed()->where('transaction_number', $transactionNumber)->get();

        //if the status is decline then update include the remarks
        //else don't update the remarks
        if ($status == 'Returned') {
            $assetRequest->each->update(['status' => $status, 'remarks' => $remarks]);
        } else {
            $assetRequest->each->update(['status' => $status, 'remarks' => null]);
        }
    }

    private function checkStatus($requestApprovalId): bool
    {
        $assetApproval = AssetApproval::where('id', $requestApprovalId)->where('status', null)->first();
        if ($assetApproval) {
            return true;
        }
        return false;
    }

    private function isAssetApprovalWithStatus($requestApprovalId, $status): bool
    {
        $assetApproval = AssetApproval::where('id', $requestApprovalId)->where('status', $status)->first();
        if ($assetApproval) {
            return true;
        }
        return false;
    }


    private function logActivity($assetApproval, $status)
    {
        $user = auth('sanctum')->user();
        $assetRequest = new AssetRequest();
        activity()
            ->causedBy($user)
            ->performedOn($assetRequest)
            ->withProperties($this->composeLogProperties($assetApproval, $status))
            ->inLog($status)
            ->tap(function ($activity) use ($user, $status, $assetApproval) {
                $activity->subject_id = $assetApproval->transaction_number;
            })
            ->log('Asset status has been changed to ' . $status . ' by ' . $user->employee_id . '.');
    }

    private function composeLogProperties($assetApproval, $status): array
    {
        // Assume approver and requester are loaded with user relation
        $approver = $assetApproval->approver->user;
        $requester = $assetApproval->requester;

        return [
            'asset_request_id' => $assetApproval->asset_request_id,
            'approver' => [
                'id' => $approver->id,
                'firstname' => $approver->firstname,
                'lastname' => $approver->lastname,
                'employee_id' => $approver->employee_id,
            ],
            'requester' => [
                'id' => $requester->id,
                'firstname' => $requester->firstname,
                'lastname' => $requester->lastname,
                'employee_id' => $requester->employee_id,
            ],
            'status' => $status,
            'remarks' => $status == 'Returned' ? $assetApproval->assetRequest->remarks ?? 'test' : null,
        ];
    }

    private function updateToNullOrVoid($transactionNumber, $status = null)
    {
        $assetApproval = AssetApproval::where('transaction_number', $transactionNumber)->get();
        if ($status == 'Void') {
            $assetApproval->each->update(['status' => 'Void']);
        } else {
            $layer = $this->getUserLayer($transactionNumber);
            $assetApproval->where('layer', '>', $layer)->each->update(['status' => null]);
        }
    }


    public function getUserLayer($transactionNumber): int
    {
        $userId = auth('sanctum')->id();
        $approverId = Approvers::where('approver_id', $userId)->value('id');

        $assetApproval = AssetApproval::where('transaction_number', $transactionNumber)->firstWhere('approver_id', $approverId);

        return $assetApproval ? $assetApproval->layer + 1 : 1;
    }
}

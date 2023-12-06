<?php

namespace App\Repositories;

use App\Models\Approvers;
use App\Models\AssetApproval;
use App\Models\AssetRequest;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

class ApprovedRequestRepository
{
    use ApiResponse;

    public function approveRequest($assetApprovalId): JsonResponse
    {

        $assetApproval = $this->findAssetApproval($assetApprovalId);
        $approverId = $this->findApproverId();
        $status = "Approved";

        if ($this->isInvalidApprover($assetApproval->approver_id, $approverId)) {
            return $this->responseUnprocessable('You are not the approver of this request');
        }

        if ($this->isAssetApprovalWithStatus($assetApprovalId, $status)) {
            return $this->responseUnprocessable('You already approve this request');
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

    //DISAPPROVE REQUEST
    public function returnRequest($assetApprovalId, $remarks = null): JsonResponse
    {

        $assetApproval = $this->findAssetApproval($assetApprovalId);
        $approverId = $this->findApproverId();
        $status = "Returned";

        if ($this->isInvalidApprover($assetApproval->approver_id, $approverId)) {
            return $this->responseUnprocessable('You are not the approver of this request');
        }
        if ($this->isAssetApprovalWithStatus($assetApprovalId, $status)) {
            return $this->responseUnprocessable('You already return this request');
        }
        if ($this->checkStatus($assetApprovalId)) {
            return $this->responseUnprocessable('Its not your turn for this request');
        }

        $this->updateAssetApprovalStatus($assetApproval, 'Returned');

        $this->updateAssetRequestStatus($assetApproval->transaction_number, 'Returned', $remarks);

        return $this->responseSuccess('Asset Request Return Successfully');
    }

    //RESUBMITTING REQUEST
    public function resubmitRequest($transactionNumber): JsonResponse
    {
        $user = auth('sanctum')->user();
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

    private function isInvalidApprover($approver_id, $approverId)
    {
        return $approver_id != $approverId;
    }

    private function updateAssetApprovalStatus($assetApproval, string $status)
    {
        $assetApproval->update(['status' => $status]);
        if ($status != 'For Approval') {
            $this->logActivity($assetApproval, $status);
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
        $assetRequest = AssetRequest::where('transaction_number', $transactionNumber)->get();

        //if the status is decline then update include the remarks
        //else don't update the remarks
        if ($status == 'Returned') {
            $assetRequest->each->update(['status' => $status, 'remarks' => $remarks]);
        } else {
            $assetRequest->each->update(['status' => $status]);
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

    private function composeLogProperties($assetApproval, $status)
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
            'remarks' => $status == 'Returned' ? $assetApproval->asset_request->remarks ?? null : null,
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

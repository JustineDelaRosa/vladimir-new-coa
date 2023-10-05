<?php

namespace App\Repositories;

use App\Models\Approvers;
use App\Models\AssetApproval;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

class ApprovedRequestRepository
{
    use ApiResponse;

    public function approveRequest(array $assetApprovalId): JsonResponse
    {
        foreach ($assetApprovalId as $id) {
            $assetApproval = $this->findAssetApproval($id);
            $approverId = $this->findApproverId();


            if ($this->isInvalidApprover($assetApproval->approver_id, $approverId)) {
                return $this->responseUnprocessable('You are not the approver of this request');
            }

            if ($this->alreadyApproved($id)) {
                return $this->responseUnprocessable('You already approved this request');
            }

            if ($this->checkStatus($id)) {
                return $this->responseUnprocessable('Its not your turn for this request');
            }

            $this->updateAssetApprovalStatus($assetApproval, 'Approved');

            $nextLayerOfApprover = $this->findNextLayerApprover($assetApproval, $assetApproval->asset_request_id,);

            if (empty($nextLayerOfApprover)) {
                $this->updateAssetRequestStatus($assetApproval->assetRequest, 'Approved');
            } else {
                $this->updateAssetApprovalStatus($nextLayerOfApprover, 'For Approval');
                $this->updateAssetRequestStatus($assetApproval->assetRequest, 'For Approval of Approver ' . ($assetApproval->layer + 1));
            }
        }
        return $this->responseSuccess('Asset Request Approved Successfully');
    }

    //DISAPPROVE REQUEST
    public function disapproveRequest($assetApprovalId): JsonResponse
    {
        foreach ($assetApprovalId as $id) {
            $assetApproval = $this->findAssetApproval($id);
            $approverId = $this->findApproverId();

            if ($this->isInvalidApprover($assetApproval->approver_id, $approverId)) {
                return $this->responseUnprocessable('You are not the approver of this request');
            }
            if ($this->alreadyApproved($id)) {
                return $this->responseUnprocessable('You already approved this request');
            }
            if ($this->checkStatus($id)) {
                return $this->responseUnprocessable('Its not your turn for this request');
            }

            $this->updateAssetApprovalStatus($assetApproval, 'Denied');

            $this->updateAssetRequestStatus($assetApproval->assetRequest, 'Denied');
        }
        return $this->responseSuccess('Asset Request Denied Successfully');
    }

    //RESUBMITTING REQUEST
    public function resubmitRequest($assetApprovalId): JsonResponse
    {
        foreach ($assetApprovalId as $id) {
            $assetApproval = AssetApproval::where('asset_request_id', $id)
                ->where('status', 'Denied')->first();

            if (!$assetApproval) {
                return $this->responseUnprocessable('Invalid Action');
            }

            $this->updateAssetRequestStatus($assetApproval->assetRequest, 'For Approval of Approver ' . ($assetApproval->layer));
            $this->updateAssetApprovalStatus($assetApproval, 'For Approval');
        }
        return $this->responseSuccess('Asset Request Resubmitted Successfully');
    }

    //VOID REQUEST
    public function voidRequest($assetApprovalId): JsonResponse
    {
        foreach ($assetApprovalId as $id) {
            $assetApproval = AssetApproval::where('id', $id)
                ->where('status', 'Denied')->first();

            if (!$assetApproval) {
                return $this->responseUnprocessable('Invalid Action');
            }

            $this->updateAssetRequestStatus($assetApproval->assetRequest, 'Void');
            $this->updateAssetApprovalStatus($assetApproval, 'Void');
        }
        return $this->responseSuccess('Asset Request Voided Successfully');
    }

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
        if($status != 'For Approval'){
        activity()
            ->causedBy(auth('sanctum')->user())
            ->performedOn($assetApproval)
            ->withProperties([
                'asset_request_id' => $assetApproval->asset_request_id,
                'approver' => [
                    'id' => $assetApproval->approver_id,
                    'firstname' => $assetApproval->approver->user->firstname,
                    'lastname' => $assetApproval->approver->user->lastname,
                    'employee_id' => $assetApproval->approver->user->employee_id,
                ],
                'requester' => [
                    'id' => $assetApproval->requester_id,
                    'firstname' => $assetApproval->requester->firstname,
                    'lastname' => $assetApproval->requester->lastname,
                ],
                'status' => $status,
            ])
            ->inLog($status)
            ->log('Asset Approval Status Updated to ' . $status . ' by ' . auth('sanctum')->user()->employee_id . '.');
        }
    }

    private function findNextLayerApprover($assetApproval, $requestId)
    {
        return AssetApproval::where([
            'requester_id' => $assetApproval->requester_id,
            'asset_request_id' => $requestId,
        ])->where('layer', $assetApproval->layer + 1)->first();
    }

    private function updateAssetRequestStatus($assetRequest, string $status)
    {
        $assetRequest->update(['status' => $status]);
//        activity()
//            ->causedBy(auth('sanctum')->user())
//            ->performedOn($assetRequest)
//            ->withProperties([
//                'asset_request_id' => $assetRequest->id,
//                'status' => $status,
//            ])
//            ->log('Asset Request Status Updated to ' . $status );
    }

    private function checkStatus($requestApprovalId): bool
    {
        $assetApproval = AssetApproval::where('id', $requestApprovalId)->where('status', null)->first();
        if ($assetApproval) {
            return true;
        }
        return false;
    }

    private function alreadyApproved($requestApprovalId): bool
    {
        $assetApproval = AssetApproval::where('id', $requestApprovalId)->where('status', 'Approved')->first();
        if ($assetApproval) {
            return true;
        }
        return false;
    }
}

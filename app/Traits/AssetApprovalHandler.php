<?php

namespace App\Traits;

trait AssetApprovalHandler
{
    public function transformIndexApproval($assetApprovals)
    {
        $assetApprovals->transform(function ($assetApproval) {
            return [
                'id' => $assetApproval->id,
                'status' => $assetApproval->status,
                'requester' => [
                    'id' => $assetApproval->requester->id,
                    'username' => $assetApproval->requester->username,
                    'employee_id' => $assetApproval->requester->employee_id,
                    'firstname' => $assetApproval->requester->firstname,
                    'lastname' => $assetApproval->requester->lastname,
                ],
                'layer' => $assetApproval->layer,
                'approver' => [
                    'id' => $assetApproval->approver->user->id,
                    'username' => $assetApproval->approver->user->username,
                    'employee_id' => $assetApproval->approver->user->employee_id,
                    'firstname' => $assetApproval->approver->user->firstname,
                    'lastname' => $assetApproval->approver->user->lastname,
                ],
                'asset_request' => [
                    'id' => $assetApproval->assetRequest->transaction_number,
                    'transaction_number' => $assetApproval->assetRequest->transaction_number,
                    'quantity_of_po' => $assetApproval->assetRequest->count(),
                    'date_requested' => $assetApproval->assetRequest->created_at,
                    'status' => $assetApproval->assetRequest->status,
                ]
            ];
        });

        return $assetApprovals;
    }

}

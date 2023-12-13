<?php

namespace App\Traits;

use App\Models\AssetRequest;

trait AssetApprovalHandler
{
    public function transformIndexApproval($assetApprovals, $transactionNumbers)
    {
        $assetApprovals->transform(function ($assetApproval) use ($transactionNumbers) {
            $quantity = [];
            foreach ($transactionNumbers as $transactionNumber) {
                $quantity[$transactionNumber] = AssetRequest::where('transaction_number', $transactionNumber)->sum('quantity');
            }
            return [
                'id' => $assetApproval->id,
                'status' => $assetApproval->status,
                'layer' => $assetApproval->layer,
                'number_of_item' => $quantity[$assetApproval->transaction_number],
                'transaction_number' => $assetApproval->transaction_number,
                'requester' => [
                    'id' => $assetApproval->requester->id ?? '-',
                    'username' => $assetApproval->requester->username ?? '-',
                    'employee_id' => $assetApproval->requester->employee_id ?? '-',
                    'firstname' => $assetApproval->requester->firstname ?? '-',
                    'lastname' => $assetApproval->requester->lastname ?? '-',
                ],
                'approver' => [
                    'id' => $assetApproval->approver->user->id ?? '-',
                    'username' => $assetApproval->approver->user->username ?? '-',
                    'employee_id' => $assetApproval->approver->user->employee_id ?? '-',
                    'firstname' => $assetApproval->approver->user->firstname ?? '-',
                    'lastname' => $assetApproval->approver->user->lastname ?? '-',
                ],
                'asset_request' => [
                    'id' => $assetApproval->assetRequest->transaction_number ?? '-',
                    'transaction_number' => $assetApproval->assetRequest->transaction_number ?? '-',
                    'date_requested' => $assetApproval->assetRequest->created_at ?? '-',
                    'status' => $assetApproval->assetRequest->status ?? '-',
                ],
            ];
        });

        return $assetApprovals;
    }
}

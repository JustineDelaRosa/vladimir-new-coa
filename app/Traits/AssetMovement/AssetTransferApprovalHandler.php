<?php

namespace App\Traits\AssetMovement;

use App\Models\AssetTransferRequest;
use Illuminate\Pagination\LengthAwarePaginator;

trait AssetTransferApprovalHandler
{
    public function approverViewing($transfer_number)
    {
        $transferRequest = AssetTransferRequest::where('transfer_number', $transfer_number)->get();
        $quantity = $transferRequest->count();
        foreach ($transferRequest as $transferRequests) {
            return [
//                'id'=> $approver ? $approver->id : null,
                'transfer_number' => $transferRequests->transfer_number,
                'description' => $transferRequests->description,
                'date_requested' => $transferRequests->created_at,
                'quantity' => $quantity,
                'status' => $transferRequests->status == 'Approved' ?
                    ($transferRequests->is_fa_approved ? 'Approved' : 'Last Approval')
                    : $transferRequests->status,
                'requester' => [
                    'id' => $transferRequests->createdBy->id,
                    'username' => $transferRequests->createdBy->username,
                    'employee_id' => $transferRequests->createdBy->employee_id,
                    'firstname' => $transferRequests->createdBy->firstname,
                    'lastname' => $transferRequests->createdBy->lastname,
                ],
//                'approver' => $approver ? [
//                    'id' => $approver->approver->id,
//                    'username' => $approver->approver->user->username,
//                    'employee_id' => $approver->approver->user->employee_id,
//                    'firstname' => $approver->approver->user->firstname,
//                    'lastname' => $approver->approver->user->lastname,
//                ] : [
//                    'id' => null,
//                    'username' => '',
//                    'employee_id' => '',
//                    'firstname' => null,
//                    'lastname' => null,
//                ],
                'asset_transfer_request' => [
                    'transfer_number' => $transferRequests->transfer_number,
                    'date_requested' => $transferRequests->created_at,
                    'status' => $transferRequests->status,
                    'description' => $transferRequests->description,
                ]
            ];
        }
    }

    public function paginate($request, $data, $perPage)
    {
        $page = $request->input('page', 1);
        $offset = ($page * $perPage) - $perPage;
        return new LengthAwarePaginator(
            array_slice($data, $offset, $perPage, true),
            count($data),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );
    }
}

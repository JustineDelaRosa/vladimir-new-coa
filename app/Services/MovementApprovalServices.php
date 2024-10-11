<?php

namespace App\Services;

use App\Models\Approvers;
use App\Models\MovementApproval;
use App\Models\MovementNumber;
use App\Models\RoleManagement;
use App\Models\Transfer;
use App\Traits\AssetMovementHandler;
use App\Traits\ReusableFunctions\Reusables;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;

class MovementApprovalServices
{
    use ApiResponse, AssetMovementHandler, Reusables;

    public function __construct()
    {
        //
    }

    public function approverViewing($request, $relation)
    {
        $request->validate([
            'for_monitoring' => 'boolean',
            'status' => 'string|in:For Approval,Approved',
        ]);

        $forMonitoring = $request->for_monitoring ?? false;
        $perPage = $request->input('per_page', null);
        $user = auth('sanctum')->user();
        $role = RoleManagement::whereId($user->role_id)->pluck('role_name');
        $adminRoles = ['Super Admin', 'Admin', 'ERP'];
        $approverId = Approvers::where('approver_id', $user->id)->pluck('id');
        $status = $request->input('status', 'For Approval');

        if (!in_array($role, $adminRoles)) {
            $forMonitoring = false;
        }

        $transferApprovalQuery = MovementApproval::query();

        if (!$forMonitoring) {
            $transferApprovalQuery->where('approver_id', $approverId);
        }

        $transferNumbers = [];
        if ($this->isUserFa()) {
            $transferNumbers = MovementNumber::where('status', 'Approved')
                ->when($status == 'Approved', function ($query) {
                    return $query->where('is_fa_approved', true);
                }, function ($query) {
                    return $query->where('is_fa_approved', false);
                })
                ->pluck('id');
//                ->toArray();
        }

        $transferApproval = $transferApprovalQuery->where('status', $status)->get();
        $transferNumbers = is_array($transferNumbers) ? $transferNumbers : [$transferNumbers];
        $transferNumbers = array_merge($transferNumbers, $transferApproval->pluck('movement_number_id')->toArray());
        $transferNumbers = Arr::flatten($transferNumbers);

        $data = MovementNumber::with($relation)->whereIn('id', $transferNumbers)->get();
        $data = $data->filter(function ($item) use ($relation) {
            return $item->$relation->isNotEmpty();
        })->values();

        // transform the data to the desired format
        $data = $this->transformData($data, $perPage, $request);
        if ($perPage !== null) {
            $dataArray = $data->toArray(); // Convert the collection to an array
            $page = $request->input('page', 1);
            $offset = ($page * $perPage) - $perPage;
            return new LengthAwarePaginator(
                array_slice($dataArray, $offset, $perPage, true),
                count($dataArray),
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        }
        return $data;
    }


    public function movementApproval($action, $movementId, $approverId)
    {
        switch (strtolower($action)) {
            case 'approve':
                $this->approveMovement($movementId, $approverId);
                break;
            case 'return':
                return 'return';
                break;
            default:
                return $this->responseUnprocessable('Invalid Action');
        }
    }

    private function approveMovement($movementId, $approverId)
    {
        $movementApproval = MovementApproval::where('movement_id', $movementId)
            ->where('approver_id', $approverId)
            ->where('status', 'For Approval')
            ->first();
        $movementNumber = MovementNumber::where('id', $movementId)->first();
        if (!$movementApproval) {
            if ($this->isUserFa()) {
                $movementNumber->where('status', 'Approved')->update(['is_fa_approved' => true]);
                return $this->responseSuccess('Approved Successfully');
            }

            return $this->responseUnprocessable('Invalid Approval');
        }
        $movementApproval->status = 'Approved';
        $movementApproval->save();

        //get the next approver based of layer
        $nextApprover = MovementApproval::where('movement_id', $movementId)
            ->where('layer', $movementApproval->layer + 1)
            ->first();

        if ($nextApprover) {
            $nextApprover->status = 'For Approval';
            $nextApprover->save();

            //update the movement number status
            $movementNumber->status = 'For Approval of Approver ' . $nextApprover->layer;
            $movementNumber->save();
        } else {
            $movementNumber->status = 'Approved';
            $movementNumber->save();
        }
    }

    private function returnMovement()
    {

    }





}

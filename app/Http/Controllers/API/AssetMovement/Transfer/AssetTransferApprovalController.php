<?php

namespace App\Http\Controllers\API\AssetMovement\Transfer;

use App\Http\Controllers\Controller;
use App\Models\Approvers;
use App\Models\AssetTransferRequest;
use App\Models\RoleManagement;
use App\Models\TransferApproval;
use App\Models\User;
use App\Traits\AssetMovement\AssetTransferApprovalHandler;
use App\Traits\AssetMovement\AssetTransferContainerHandler;
use App\Traits\AssetMovement\TransferRequestHandler;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class AssetTransferApprovalController extends Controller
{
    use ApiResponse, TransferRequestHandler, AssetTransferContainerHandler, AssetTransferApprovalHandler;

    public function index(Request $request)
    {
        $request->validate([
            'for_monitoring' => 'boolean',
            'status' => 'string|in:For Approval,Approved',
        ]);

        $forMonitoring = $request->for_monitoring ?? false;
        $perPage = $request->input('per_page', null);
        $search = $request->input('search', null);
        $user = auth('sanctum')->user();
        $role = RoleManagement::whereId($user->role_id)->pluck('role_name');
        $adminRoles = ['Super Admin', 'Admin', 'ERP'];
        $approverId = Approvers::where('approver_id', $user->id)->pluck('id');
        $status = $request->input('status', 'For Approval');

        if (!in_array($role, $adminRoles)) {
            $forMonitoring = false;
        }

        $transferApprovalQuery = TransferApproval::query();

        if (!$forMonitoring) {
            $transferApprovalQuery->where('approver_id', $approverId);
        }

        $transferNumbers = [];
        if ($this->isUserFa()) {
            $transferNumbers = AssetTransferRequest::where('status', 'Approved')
                ->where('is_fa_approved', false)
                ->pluck('transfer_number');
        }

        $transferApproval = $transferApprovalQuery->where('status', $status)->useFilters()->dynamicPaginate();

        $transferNumbers = is_array($transferNumbers) ? $transferNumbers : [$transferNumbers];
        $transferNumbers = array_merge($transferNumbers, $transferApproval->pluck('transfer_number')->toArray());

        $data = AssetTransferRequest::with('transferApproval', 'transferApproval.approver', 'transferApproval.approver.user')
            ->whereIn('transfer_number', $transferNumbers)
            ->useFilters()
            ->get()
            ->map(function ($transferRequest) use ($request) {
                return $this->approverViewing($transferRequest->transfer_number);
            });

        if ($perPage !== null) {
            $data = $this->paginate($request, $data->toArray(), $perPage);
        }

        return $data;
    }

    public function show($transferNumber)
    {
        $transferRequest = AssetTransferRequest::where('transfer_number', $transferNumber)->get();
        return $this->setContainerResponse($transferRequest);
    }


    public function transferRequestAction(Request $request): \Illuminate\Http\JsonResponse
    {
        $action = $request->action;
        $transferNumber = $request->transfer_number;
        $remarks = $request->remarks;

        return $this->requestAction($action, $transferNumber, 'transfer_number', new AssetTransferRequest(), new TransferApproval(), $remarks);
    }
}

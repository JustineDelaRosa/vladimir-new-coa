<?php

namespace App\Http\Controllers\API;

use App\Models\Approvers;
use App\Models\AssetApproval;
use App\Models\AssetRequest;
use App\Models\RoleManagement;
use App\Repositories\ApprovedRequestRepository;
use App\Traits\AssetApprovalHandler;
use App\Traits\AssetRequestHandler;
use Essa\APIToolKit\Api\ApiResponse;
use Essa\APIToolKit\Filters\DTO\FiltersDTO;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;

use App\Http\Requests\AssetApproval\CreateAssetApprovalRequest;
use App\Http\Requests\AssetApproval\UpdateAssetApprovalRequest;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\Activitylog\Models\Activity;

class AssetApprovalController extends Controller
{
    use ApiResponse, AssetApprovalHandler, assetRequestHandler;

    private ApprovedRequestRepository $approveRequestRepository;

    public function __construct(ApprovedRequestRepository $approveRequestRepository)
    {
        $this->approveRequestRepository = $approveRequestRepository;
    }

    public function index(Request $request)
    {
        $request->validate([
            'for_monitoring' => 'boolean',
            'status' => 'string|in:For Approval,Approved', // or any other statuses you might have
        ]);

        $forMonitoring = $request->for_monitoring ?? false;
        $user = auth('sanctum')->user();
        $role = RoleManagement::whereId($user->role_id)->value('role_name');
        $adminRoles = ['Super Admin', 'Admin', 'ERP'];
        $approverId = Approvers::where('approver_id', $user->id)->value('id');
        $status = $request->input('status', 'For Approval');

        if (!in_array($role, $adminRoles)) {
            $forMonitoring = false;
        }

        $assetApprovalsQuery = AssetApproval::query();
        if (!$forMonitoring ) {
            $assetApprovalsQuery->where('approver_id', $approverId);
        }

        $assetApprovals = $assetApprovalsQuery->where('status', $status)->useFilters()->dynamicPaginate();


        $transactionNumbers = $assetApprovals->map(function ($item) {
            return $item->transaction_number;
        });

        return $this->transformIndexApproval($assetApprovals, $transactionNumbers);
    }

    public function store(CreateAssetApprovalRequest $request): JsonResponse
    {
        $assetApproval = AssetApproval::create($request->all());

        return $this->responseCreated('AssetApproval created successfully', $assetApproval);
    }

    public function show($id)
    {
        $user = auth('sanctum')->user();
        $role = RoleManagement::whereId($user->role_id)->value('role_name');
        $adminRoles = ['Super Admin', 'Admin', 'ERP'];
        $approverId = Approvers::where('approver_id', $user->id)->value('id');
        $assetApprovalQuery = AssetApproval::query()->where('status', 'For Approval');
        if (!in_array($role, $adminRoles)) {
            $assetApprovalQuery->where('approver_id', $approverId);
        }
        $assetApproval = $assetApprovalQuery->where('id', $id)->first();
        if (!$assetApproval) {
            return $this->responseUnprocessable('Unauthorized Access');
        }
        $assetRequest = AssetRequest::where('transaction_number', $assetApproval->transaction_number)
            ->get();
        return $this->transformShowAssetRequest($assetRequest);
    }

    public function update(UpdateAssetApprovalRequest $request, AssetApproval $assetApproval): JsonResponse
    {
        $assetApproval->update($request->all());

        return $this->responseSuccess('AssetApproval updated Successfully', $assetApproval);
    }

    public function destroy(AssetApproval $assetApproval): JsonResponse
    {
        $assetApproval->delete();

        return $this->responseDeleted();
    }

    public function handleRequest(CreateAssetApprovalRequest $request): JsonResponse
    {
        $assetApprovalIds = $request->asset_approval_id;
//        $assetRequestIds = $request->asset_request_id;
        $remarks = $request->remarks;
        $action = ucwords($request->action);

        switch ($action) {
            case 'Approve':
                return $this->approveRequestRepository->approveRequest($assetApprovalIds);
                break;
            case 'Return':
                return $this->approveRequestRepository->returnRequest($assetApprovalIds, $remarks);
                break;
//          case 'Void':
//              return $this->approveRequestRepository->voidRequest($assetRequestIds);
//              break;
            default:
                return $this->responseUnprocessable('Invalid Action');
                break;
        }
    }

//    public function approveRequest(Request $request, $id)
//    {
//        $assetApproval = AssetApproval::find($id);
//
//        //check if the logged in user is the approver
//        $user = auth('sanctum')->user();
//        $approverId = Approvers::where('approver_id', $user->id)->value('id');
//        if ($assetApproval->approver_id != $approverId) {
//            return $this->responseUnprocessable('You are not the approver of this request');
//        }
//
//        $requesterId = $assetApproval->requester_id;
//
//        //update the status of the asset request
//        $assetApproval->update([
//            'status' => 'Approved',
//        ]);
//
//        //update the status of the next layer of approver
//        $nextLayerOfApprover = AssetApproval::where('requester_id', $requesterId)->where('layer', $assetApproval->layer + 1)->first();
//        //if no next layer of approver, update the status of the asset request to Approved
//        if (!$nextLayerOfApprover) {
//            $assetRequest = $assetApproval->assetRequest;
//            $assetRequest->update([
//                'status' => 'Approved',
//            ]);
//            return $this->responseSuccess('Asset Request Approved Successfully');
//        }
//
//        if ($nextLayerOfApprover) {
//            $nextLayerOfApprover->update([
//                'status' => 'For Approval',
//            ]);
//        }
//
//        //update the status of the asset request
//        $assetRequest = $assetApproval->assetRequest;
//        $assetRequest->update([
//            'status' => 'For Approval of Approver ' . ($assetApproval->layer + 1),
//        ]);
//
//
//        return $this->responseSuccess('Asset Request Approved Successfully');
//    }

}

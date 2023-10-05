<?php

namespace App\Http\Controllers\API;

use App\Models\Approvers;
use App\Models\AssetApproval;
use App\Models\RoleManagement;
use App\Repositories\ApprovedRequestRepository;
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
    use ApiResponse;

    private $approveRequestRepository;

    public function __construct(ApprovedRequestRepository $approveRequestRepository)
    {
        $this->approveRequestRepository = $approveRequestRepository;
    }

    public function index()
    {
        $user = auth('sanctum')->user();
        $role = RoleManagement::whereId($user->role_id)->value('role_name');
        $adminRoles = ['Super Admin', 'Admin', 'ERP'];
        $approverId = Approvers::where('approver_id', $user->id)->value('id');

        $assetApprovalsQuery = AssetApproval::query();
        if (!in_array($role, $adminRoles)) {
            $assetApprovalsQuery->where('approver_id', $approverId);
        }

        $assetApprovals = $assetApprovalsQuery->where('status', 'For Approval')->useFilters()->dynamicPaginate();
        $assetApprovals->transform(function($assetApproval){
            return[
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
                    'id' => $assetApproval->assetRequest->id,
                    'status' => $assetApproval->assetRequest->status,
                    'requester' => [
                        'id' => $assetApproval->assetRequest->requester->id,
                        'username' => $assetApproval->assetRequest->requester->username,
                        'employee_id' => $assetApproval->assetRequest->requester->employee_id,
                        'firstname' => $assetApproval->assetRequest->requester->firstname,
                        'lastname' => $assetApproval->assetRequest->requester->lastname,
                    ],
                    'type_of_request' => [
                        'id' => $assetApproval->assetRequest->typeOfRequest->id,
                        'type_of_request_name' => $assetApproval->assetRequest->typeOfRequest->type_of_request_name,
                    ],
                    'capex' => [
                        'id' => $assetApproval->assetRequest->capex->id ?? '-',
                        'capex_name' => $assetApproval->assetRequest->capex->capex_name ?? '-',
                    ],
                    'sub_capex' => [
                        'id' => $assetApproval->assetRequest->subCapex->id ?? '-',
                        'sub_capex_name' => $assetApproval->assetRequest->subCapex->sub_capex_name ?? '-',
                    ],
                    'asset_description' => $assetApproval->assetRequest->asset_description,
                    'asset_specification' => $assetApproval->assetRequest->asset_specification ?? '-',
                    'accountability' => $assetApproval->assetRequest->accountability,
                    'accountable' => $assetApproval->assetRequest->accountable ?? '-',
                    'cellphone_number' => $assetApproval->assetRequest->cellphone_number ?? '-',
                    'brand' => $assetApproval->assetRequest->brand ?? '-',
                ],
            ];
        });

        return $assetApprovals;
    }

    public function store(CreateAssetApprovalRequest $request): JsonResponse
    {
        $assetApproval = AssetApproval::create($request->all());

        return $this->responseCreated('AssetApproval created successfully', $assetApproval);
    }

    public function show($id): JsonResponse
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
            return $this->responseUnprocessable('Unauthorized for asset approval');
        }

        return $this->responseSuccess('Successfully fetched AssetApproval', [
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
                'id' => $assetApproval->assetRequest->id,
                'status' => $assetApproval->assetRequest->status,
                'requester' => [
                    'id' => $assetApproval->assetRequest->requester->id,
                    'username' => $assetApproval->assetRequest->requester->username,
                    'employee_id' => $assetApproval->assetRequest->requester->employee_id,
                    'firstname' => $assetApproval->assetRequest->requester->firstname,
                    'lastname' => $assetApproval->assetRequest->requester->lastname,
                ],
                'type_of_request' => [
                    'id' => $assetApproval->assetRequest->typeOfRequest->id,
                    'type_of_request_name' => $assetApproval->assetRequest->typeOfRequest->type_of_request_name,
                ],
                'capex' => [
                    'id' => $assetApproval->assetRequest->capex->id ?? '-',
                    'capex_name' => $assetApproval->assetRequest->capex->capex_name ?? '-',
                ],
                'sub_capex' => [
                    'id' => $assetApproval->assetRequest->subCapex->id ?? '-',
                    'sub_capex_name' => $assetApproval->assetRequest->subCapex->sub_capex_name ?? '-',
                ],
                'asset_description' => $assetApproval->assetRequest->asset_description,
                'asset_specification' => $assetApproval->assetRequest->asset_specification ?? '-',
                'accountability' => $assetApproval->assetRequest->accountability,
                'accountable' => $assetApproval->assetRequest->accountable ?? '-',
                'cellphone_number' => $assetApproval->assetRequest->cellphone_number ?? '-',
                'brand' => $assetApproval->assetRequest->brand ?? '-',
            ],
        ]);
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
        $action = ucwords($request->action);

        switch ($action) {
            case 'Approved':
                return $this->approveRequestRepository->approveRequest($assetApprovalIds);
                break;
            case 'Denied':
                return $this->approveRequestRepository->disapproveRequest($assetApprovalIds);
                break;
            case 'Void':
                return $this->approveRequestRepository->voidRequest($assetApprovalIds);
                break;
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

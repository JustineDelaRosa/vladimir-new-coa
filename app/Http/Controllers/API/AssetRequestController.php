<?php

namespace App\Http\Controllers\API;

use App\Models\ApproverLayer;
use App\Models\AssetApproval;
use App\Models\AssetRequest;
use App\Models\RoleManagement;
use App\Models\SubCapex;
use App\Transformers\AssetRequestTransformers\AssetRequestTransformers;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;

use App\Http\Requests\AssetRequest\CreateAssetRequestRequest;
use App\Http\Requests\AssetRequest\UpdateAssetRequestRequest;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class AssetRequestController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $user = auth('sanctum')->user();
        $role = RoleManagement::whereId($user->role_id)->value('role_name');
        $adminRoles = ['Super Admin', 'Admin', 'ERP'];

        $assetRequestsQuery = AssetRequest::query();

        if (!in_array($role, $adminRoles)) {
            $assetRequestsQuery->where('requester_id', $user->id);
        }

        $assetRequestsQuery->orderBy(DB::raw("CASE WHEN status = 'Approved' THEN 1 ELSE 0 END"), 'ASC');
        $assetRequests = $assetRequestsQuery->useFilters()->dynamicPaginate();
        $assetRequests->transform(function ($assetRequest) {
            $approverUser = $assetRequest->currentApprover->first()->approver->user ?? null;

            $requester = $assetRequest->requester;
            $typeOfRequest = $assetRequest->typeOfRequest;
            $capex = $assetRequest->capex;
            $subCapex = $assetRequest->subCapex;

            return [
                'id' => $assetRequest->id,
                'status' => $assetRequest->status,
                'current_approver' => [
//                    'id' => $approverUser->id ?? '-',
                    'username' => $approverUser->username ?? '-',
                    'employee_id' => $approverUser->employee_id ?? '-',
                    'firstname' => $approverUser->firstname ?? '-',
                    'lastname' => $approverUser->lastname ?? '-',
                ],
                'requester' => [
                    'id' => $requester->id,
                    'username' => $requester->username,
                    'employee_id' => $requester->employee_id,
                    'firstname' => $requester->firstname,
                    'lastname' => $requester->lastname,
                ],
                'type_of_request' => [
                    'id' => $typeOfRequest->id,
                    'type_of_request_name' => $typeOfRequest->type_of_request_name,
                ],
                'capex' => [
                    'id' => $capex->id ?? '-',
                    'capex_name' => $capex->capex_name ?? '-',
                ],
                'sub_capex' => [
                    'id' => $subCapex->id ?? '-',
                    'sub_capex_name' => $subCapex->sub_capex_name ?? '-',
                ],
                'asset_description' => $assetRequest->asset_description,
                'asset_specification' => $assetRequest->asset_specification ?? '-',
                'accountability' => $assetRequest->accountability,
                'accountable' => $assetRequest->accountable ?? '-',
                'cellphone_number' => $assetRequest->cellphone_number ?? '-',
                'brand' => $assetRequest->brand ?? '-',
            ];
        });

        return $this->responseSuccess('Asset Requests retrieved successfully', $assetRequests);
    }

    public function store(CreateAssetRequestRequest $request)
    {
        $requestCount = $request->quantity;
        $requester_id = $request->requester_id;
        $type_of_request_id = $request->type_of_request_id;
        $sub_capex_id = $request->sub_capex_id;
        $asset_description = $request->asset_description;
        $asset_specification = $request->asset_specification;
        $accountability = $request->accountability;
        $accountable = $request->accountable;
        $cellphone_number = $request->cellphone_number;
        $brand = $request->brand;

        $approverLayers = ApproverLayer::where('requester_id', $request->requester_id)
            ->orderBy('layer', 'asc')
            ->get();

        $haveApprovers = ApproverLayer::where('requester_id', $request->requester_id)->exists();
        if (!$haveApprovers) {
            return $this->responseUnprocessable('You have no approvers yet. Please contact support.');
        }
        //TODO:check if the user still have pending request
//        $pendingRequest = AssetRequest::where('requester_id', $request->requester_id)->where('status', '!=', 'Approved')->exists();
//        if ($pendingRequest) {
//            return $this->responseUnprocessable('You still have pending request.');
//        }

//        $assetRequest = AssetRequest::create($request->all());

        foreach (range(1, $requestCount) as $index) {
            $capex_id = isset($request['sub_capex_id']) ? SubCapex::find($request['sub_capex_id'])->capex_id : null;

            $assetRequest = AssetRequest::create(compact(
                'requester_id',
                'type_of_request_id',
                'capex_id',
                'sub_capex_id',
                'asset_description',
                'asset_specification',
                'accountability',
                'accountable',
                'cellphone_number',
                'brand'
            ));

            if ($assetRequest) {
                $firstLayerFlag = true; // Introduce a flag to identify the first layer

                foreach ($approverLayers as $layer) {
                    $approver_id = $layer->approver_id;
                    $layer = $layer->layer;

                    $status = $firstLayerFlag ? 'For Approval' : null;
                    $asset_request_id = $assetRequest->id;
                    AssetApproval::create(compact(
                        'asset_request_id',
                        'approver_id',
                        'requester_id',
                        'layer',
                        'status'
                    ));
                    $firstLayerFlag = false;
                }
            }
        }

//        $assetRequest = AssetRequest::create([
//            'requester_id' => $request->requester_id,
//            'type_of_request_id' => $request->type_of_request_id,
//            'capex_id' => isset($request['sub_capex_id']) ? SubCapex::find($request['sub_capex_id'])->capex_id : null,
//            'sub_capex_id' => $request->sub_capex_id,
//            'asset_description' => $request->asset_description,
//            'asset_specification' => $request->asset_specification,
//            'accountability' => $request->accountability,
//            'accountable' => $request->accountable,
//            'cellphone_number' => $request->cellphone_number,
//            'brand' => $request->brand,
//        ]);
//
//        if ($assetRequest) {
//            $approverLayer = ApproverLayer::where('requester_id', $request->requester_id)->orderBy('layer', 'asc')->get();
//
//            $firstLayerFlag = true; // Introduce a flag to identify the first layer
//
//            foreach ($approverLayer as $layer) {
//                $approver_id = $layer->approver_id;
//                $layer_number = $layer->layer;
//
//                $status = $firstLayerFlag ? 'For Approval' : null;
//                $assetApproval = AssetApproval::query();
//                $createAssetApproval = $assetApproval->create([
//                    'asset_request_id' => $assetRequest->id,
//                    'approver_id' => $approver_id,
//                    'requester_id' => $request->requester_id,
//                    'layer' => $layer_number,
//                    'status' => $status,
//                ]);
//                $firstLayerFlag = false;
//            }
//            return $this->responseCreated('AssetRequest created successfully', $assetRequest);
//
//        }
        return $this->responseCreated('AssetRequest created successfully', $assetRequest);
    }

    public function show(AssetRequest $assetRequest): JsonResponse
    {
        return $this->responseSuccess(null, [
            'id' => $assetRequest->id,
            'status' => $assetRequest->status,
            'current_approver' => $assetRequest->currentApprover->first()->approver->user ?? null,
            'requester' => [
                'id' => $assetRequest->requester->id,
                'username' => $assetRequest->requester->username,
                'employee_id' => $assetRequest->requester->employee_id,
                'firstname' => $assetRequest->requester->firstname,
                'lastname' => $assetRequest->requester->lastname,
            ],
            'type_of_request' => [
                'id' => $assetRequest->typeOfRequest->id,
                'type_of_request_name' => $assetRequest->typeOfRequest->type_of_request_name,
            ],
            'capex' => [
                'id' => $assetRequest->capex->id ?? '-',
                'capex_name' => $assetRequest->capex->capex_name ?? '-',
            ],
            'sub_capex' => [
                'id' => $assetRequest->subCapex->id ?? '-',
                'sub_capex_name' => $assetRequest->subCapex->sub_capex_name ?? '-',
            ],
            'asset_description' => $assetRequest->asset_description,
            'asset_specification' => $assetRequest->asset_specification ?? '-',
            'accountability' => $assetRequest->accountability,
            'accountable' => $assetRequest->accountable ?? '-',
            'cellphone_number' => $assetRequest->cellphone_number ?? '-',
            'brand' => $assetRequest->brand ?? '-',
        ]);
    }

    public function update(UpdateAssetRequestRequest $request, AssetRequest $assetRequest): JsonResponse
    {
        $assetRequest->update($request->all());
//        $assetRequest = AssetRequest::update([
//            'requester_id' => $request->requester_id,
//            'type_of_request_id' => $request->type_of_request_id,
//            'capex_id' => isset($request['sub_capex_id']) ? SubCapex::find($request['sub_capex_id'])->capex_id : null,
//            'sub_capex_id' => $request->sub_capex_id,
//            'asset_description' => $request->asset_description,
//            'asset_specification' => $request->asset_specification,
//            'accountability' => $request->accountability,
//            'accountable' => $request->accountable,
//            'cellphone_number' => $request->cellphone_number,
//            'brand' => $request->brand,
//        ]);

        return $this->responseSuccess('AssetRequest updated Successfully', [
            'id' => $assetRequest->id,
            'status' => $assetRequest->status,
            'requester' => [
                'id' => $assetRequest->requester->id,
                'username' => $assetRequest->requester->username,
                'employee_id' => $assetRequest->requester->employee_id,
                'firstname' => $assetRequest->requester->firstname,
                'lastname' => $assetRequest->requester->lastname,
            ],
            'type_of_request' => [
                'id' => $assetRequest->typeOfRequest->id,
                'type_of_request_name' => $assetRequest->typeOfRequest->type_of_request_name,
            ],
            'capex' => [
                'id' => $assetRequest->capex->id ?? '-',
                'capex_name' => $assetRequest->capex->capex_name ?? '-',
            ],
            'sub_capex' => [
                'id' => $assetRequest->subCapex->id ?? '-',
                'sub_capex_name' => $assetRequest->subCapex->sub_capex_name ?? '-',
            ],
            'asset_description' => $assetRequest->asset_description,
            'asset_specification' => $assetRequest->asset_specification ?? '-',
            'accountability' => $assetRequest->accountability,
            'accountable' => $assetRequest->accountable ?? '-',
            'cellphone_number' => $assetRequest->cellphone_number ?? '-',
            'brand' => $assetRequest->brand ?? '-',
        ]);
    }

    public function destroy(AssetRequest $assetRequest): JsonResponse
    {
        $assetRequest->delete();

        return $this->responseDeleted();
    }

}

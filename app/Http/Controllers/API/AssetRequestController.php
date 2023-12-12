<?php

namespace App\Http\Controllers\API;

use App\Models\ApproverLayer;
use App\Models\Approvers;
use App\Models\AssetApproval;
use App\Models\AssetRequest;
use App\Models\Company;
use App\Models\Department;
use App\Models\DepartmentUnitApprovers;
use App\Models\RequestContainer;
use App\Models\RoleManagement;
use App\Models\SubCapex;
use App\Models\SubUnit;
use App\Repositories\ApprovedRequestRepository;
use App\Traits\AssetRequestHandler;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;

use App\Http\Requests\AssetRequest\CreateAssetRequestRequest;
use App\Http\Requests\AssetRequest\UpdateAssetRequestRequest;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\Activitylog\Models\Activity;

class AssetRequestController extends Controller
{
    use ApiResponse, AssetRequestHandler;

    private $approveRequestRepository;

    public function __construct(ApprovedRequestRepository $approveRequestRepository)
    {
        $this->approveRequestRepository = $approveRequestRepository;
    }

    public function index(Request $request)
    {
        $request->validate([
            'for_monitoring' => 'boolean',
        ]);

        $perPage = $request->input('per_page', null);

        $forMonitoring = $request->for_monitoring ?? false;

        $requesterId = auth('sanctum')->user()->id;
        $forMonitoring = $request->for_monitoring ?? false;
        $role = RoleManagement::whereId($requesterId)->value('role_name');
        $adminRoles = ['Super Admin', 'Admin', 'ERP'];

        if (!in_array($role, $adminRoles)) {
            $forMonitoring = false;
        }

        $assetRequest = AssetRequest::query()->orderByDesc('created_at')->useFilters();
        if (!$forMonitoring) {
            $assetRequest->where('requester_id', $requesterId);
        }

        $assetRequest = $assetRequest->get()->groupBy('transaction_number')->map(function ($assetRequestCollection) {
            $assetRequest = $assetRequestCollection->first();
            $assetRequest->quantity = $assetRequestCollection->sum('quantity');
            return $this->transformIndexAssetRequest($assetRequest);
        })->values();

        if ($perPage !== null) {
            $page = $request->input('page', 1);
            $offset = ($page * $perPage) - $perPage;
            $assetRequest = new LengthAwarePaginator(
                $assetRequest->slice($offset, $perPage)->values(),
                $assetRequest->count(),
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        }
        return $assetRequest;
    }

    public function store(CreateAssetRequestRequest $request)
    {

        $userRequest = $request->userRequest;
        $requesterId = auth('sanctum')->user()->id;
        $transactionNumber = AssetRequest::generateTransactionNumber();
        $departmentUnitApprovers = DepartmentUnitApprovers::with('approver')->where('subunit_id', $userRequest[0]['subunit_id'])
            ->orderBy('layer', 'asc')
            ->get();

        $layerIds = $departmentUnitApprovers->map(function ($approverObject) {
            return $approverObject->approver->approver_id;
        })->toArray();

        $isRequesterApprover = in_array($requesterId, $layerIds);
        $requesterLayer = array_search($requesterId, $layerIds) + 1;
        $maxLayer = $departmentUnitApprovers->max('layer');
        $isLastApprover = $maxLayer == $requesterLayer;

        foreach ($userRequest as $request) {
            $assetRequest = AssetRequest::create([
                'status' => $isLastApprover
                    ? 'Approved'
                    : ($isRequesterApprover
                        ? 'For Approval of Approver ' . ($requesterLayer + 1)
                        : 'For Approval'),
                'requester_id' => $requesterId,
                'transaction_number' => $transactionNumber,
                'reference_number' => (new AssetRequest)->generateReferenceNumber(),
                'type_of_request_id' => $request['type_of_request_id']['id'],
                'attachment_type' => $request['attachment_type'],
//                'charged_department_id' => $request['charged_department_id'],
                'subunit_id' => $request['subunit_id']['id'],
                'location_id' => $request['location_id']['id'],
                'account_title_id' => $request['account_title_id']['id'],
                'accountability' => $request['accountability'],
                'company_id' => $request['department_id']['company']['company_id'],
                'department_id' => $request['department_id']['id'],
                'accountable' => $request['accountable'] ?? null,
                'asset_description' => $request['asset_description'],
                'asset_specification' => $request['asset_specification'] ?? null,
                'cellphone_number' => $request['cellphone_number'] ?? null,
                'brand' => $request['brand'] ?? null,
                'quantity' => $request['quantity'],
            ]);

            $fileKeys = ['letter_of_request', 'quotation', 'specification_form', 'tool_of_trade', 'other_attachments'];

            foreach ($fileKeys as $fileKey) {
                if (isset($request[$fileKey])) {
                    $files = is_array($request[$fileKey]) ? $request[$fileKey] : [$request[$fileKey]];
                    foreach ($files as $file) {
                        $assetRequest->addMedia($file)->toMediaCollection($fileKey);
                    }
                }
            }
//            if(isset($request['letter_of_request'])) {
//                $assetRequest->addMedia($request['letter_of_request'])->toMediaCollection('letter_of_request');
//            }
//
//            if (isset($request['quotation'])) {
//                $assetRequest->addMedia($request['quotation'])->toMediaCollection('quotation');
//            }
//            if (isset($request['specification_form'])) {
//                $assetRequest->addMedia($request['specification_form'])->toMediaCollection('specification_form');
//            }
//            if (isset($request['tool_of_trade'])) {
//                $assetRequest->addMedia($request['tool_of_trade'])->toMediaCollection('tool_of_trade');
//            }
//            if (isset($request['other_attachments'])) {
//                $assetRequest->addMedia($request['other_attachments'])->toMediaCollection('other_attachments');
//            }
        }

        foreach ($departmentUnitApprovers as $departmentUnitApprover) {
            $approver_id = $departmentUnitApprover->approver_id;
            $layer = $departmentUnitApprover->layer;

            // initial status
            $status = null;

            // if the requester is the approver, decide on status
            if ($isRequesterApprover) {
                if ($layer == $requesterLayer || $layer < $requesterLayer) {
                    $status = "Approved";
                } elseif ($layer == $requesterLayer + 1) {
                    $status = "For Approval";
                }
            } elseif ($layer == 1) { // if the requester is not an approver, only the first layer should be "For Approval"
                $status = "For Approval";
            }

            AssetApproval::create([
                'transaction_number' => $assetRequest->transaction_number,
                'approver_id' => $approver_id,
                'requester_id' => $requesterId,
                'layer' => $layer,
                'status' => $status,
            ]);
        }

        return $this->responseCreated('AssetRequest created successfully');
    }

    public function show($transactionNumber)
    {
        //For Specific Viewing of Asset Request with the same transaction number
        $requestorId = auth('sanctum')->user()->id;
        //check if the user is approver
        $approverCheck = Approvers::where('approver_id', $requestorId)->first();
        if($approverCheck) {
            $assetRequest = AssetRequest::where('transaction_number', $transactionNumber)
                ->get();
        } else {
            $assetRequest = AssetRequest::where('transaction_number', $transactionNumber)
                ->where('requester_id', $requestorId)
                ->get();
        }

        if ($assetRequest->isEmpty()) {
            return $this->responseUnprocessable('Asset Request not found.');
        }

        return $this->transformShowAssetRequest($assetRequest);
    }

    public function update(UpdateAssetRequestRequest $request, $referenceNumber)
    {

        $assetRequest = $this->getAssetRequest('reference_number', $referenceNumber);
        if (!$assetRequest) {
            return $this->responseUnprocessable('Asset Request not found.');
        }

        $updateResult = $this->updateAssetRequest($assetRequest, $request);
        if ($updateResult) {
            $this->handleMediaAttachments($assetRequest, $request);
        }
        return $this->responseSuccess('AssetRequest updated Successfully');
    }

    public function destroy(AssetRequest $assetRequest): JsonResponse
    {
        $assetRequest->delete();

        return $this->responseDeleted();
    }

    public function resubmitRequest(CreateAssetRequestRequest $request)
    {
        $transactionNumber = $request->transaction_number;
        $resubmitCheck = AssetRequest::where('transaction_number', $transactionNumber)
            ->where('status', 'Returned')
            ->first();
        if (!$resubmitCheck) {
            return $this->responseUnprocessable('Invalid Action, Asset Request is not returned.');
        }
        return $this->approveRequestRepository->resubmitRequest($transactionNumber);
    }

    public function updateRequest(Request $request, $referenceNumber)
    {
//        return $request->all();

        $assetRequest = $this->getAssetRequest('reference_number', $referenceNumber);
        if (!$assetRequest) {
            return $this->responseUnprocessable('Asset Request not found.');
        }

        $updateResult = $this->updateAssetRequest($assetRequest, $request);
        if ($updateResult) {
            $this->handleMediaAttachments($assetRequest, $request);
        }

        return $this->responseSuccess('AssetRequest updated Successfully');
    }

    public function removeRequestItem($transactionNumber, $referenceNumber = null)
    {

        if ($transactionNumber && $referenceNumber) {
//            return 'both';
            return $this->voidRequestItem($referenceNumber);
        }
        if ($transactionNumber) {

//            return 'single';
            return $this->voidAssetRequest($transactionNumber);
        }
    }

    public function moveData()
    {
        // Get the requester id from the request
        $requesterId = auth('sanctum')->user()->id;
        $transactionNumber = AssetRequest::generateTransactionNumber();


        // Get the items from Request-container
        $items = RequestContainer::where('requester_id', $requesterId)->get();


        foreach ($items as $item) {
            // For each item, create an AssetRequest
            $assetRequest = new AssetRequest;

            $assetRequest->status = $item->status;
            $assetRequest->requester_id = $item->requester_id;
            $assetRequest->type_of_request_id = $item->type_of_request_id;
            $assetRequest->attachment_type = $item->attachment_type;
            $assetRequest->subunit_id = $item->subunit_id;
            $assetRequest->location_id = $item->location_id;
            $assetRequest->account_title_id = $item->account_title_id;
            $assetRequest->accountability = $item->accountability;
            $assetRequest->company_id = $item->company_id;
            $assetRequest->department_id = $item->department_id;
            $assetRequest->accountable = $item->accountable;
            $assetRequest->asset_description = $item->asset_description;
            $assetRequest->asset_specification = $item->asset_specification;
            $assetRequest->cellphone_number = $item->cellphone_number;
            $assetRequest->brand = $item->brand;
            $assetRequest->quantity = $item->quantity;

            // Add transaction number and reference number
            $assetRequest->transaction_number = $transactionNumber;
            $assetRequest->reference_number = (new AssetRequest)->generateReferenceNumber();

            $assetRequest->save();

            // Get the media from RequestContainer and put it in AssetRequest
            $fileKeys = ['letter_of_request', 'quotation', 'specification_form', 'tool_of_trade', 'other_attachments'];

            foreach ($fileKeys as $fileKey) {
                $media = $item->getMedia($fileKey);
                foreach ($media as $file) {
                    $file->copy($assetRequest, $fileKey);
                }
            }

            // Delete the item from RequestContainer
            $item->delete();
        }

        $departmentUnitApprovers = DepartmentUnitApprovers::with('approver')->where('subunit_id', $items[0]->subunit_id)
            ->orderBy('layer', 'asc')
            ->get();

        $layerIds = $departmentUnitApprovers->map(function ($approverObject) {
            return $approverObject->approver->approver_id;
        })->toArray();
        $isRequesterApprover = in_array($requesterId, $layerIds);
        $requesterLayer = array_search($requesterId, $layerIds) + 1;

        foreach ($departmentUnitApprovers as $departmentUnitApprover) {
            $approver_id = $departmentUnitApprover->approver_id;
            $layer = $departmentUnitApprover->layer;

            // initial status
            $status = null;

            // if the requester is the approver, decide on status
            if ($isRequesterApprover) {
                if ($layer == $requesterLayer || $layer < $requesterLayer) {
                    $status = "Approved";
                } elseif ($layer == $requesterLayer + 1) {
                    $status = "For Approval";
                }
            } elseif ($layer == 1) { // if the requester is not an approver, only the first layer should be "For Approval"
                $status = "For Approval";
            }

            AssetApproval::create([
                'transaction_number' => $assetRequest->transaction_number,
                'approver_id' => $approver_id,
                'requester_id' => $requesterId,
                'layer' => $layer,
                'status' => $status,
            ]);
        }

        return $this->responseSuccess('Successfully requested');
    }

    public function showById($id)
    {
        $assetRequest = AssetRequest::find($id);
        if (!$assetRequest) {
            return $this->responseUnprocessable('Asset Request not found.');
        }
        return $this->transformForSingleItemOnly($assetRequest);
    }

    public function getPerRequest($transactionNumber)
    {

        $assetRequest = AssetRequest::where('transaction_number', $transactionNumber)->orderByDesc('created_at')
            ->useFilters()->get()->groupBy('transaction_number')->map(function ($assetRequestCollection) {
                $assetRequest = $assetRequestCollection->first();
                //sum all the quantity per group
                $assetRequest->quantity = $assetRequestCollection->sum('quantity');
                return $this->transformIndexAssetRequest($assetRequest);
            })->values();

        return $assetRequest;
    }
}

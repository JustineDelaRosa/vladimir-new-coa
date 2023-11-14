<?php

namespace App\Http\Controllers\API;

use App\Models\ApproverLayer;
use App\Models\AssetApproval;
use App\Models\AssetRequest;
use App\Models\DepartmentUnitApprovers;
use App\Models\RoleManagement;
use App\Models\SubCapex;
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

        $assetRequest = $this->transformIndexAssetRequest($request);
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

        foreach ($userRequest as $request) {
            $assetRequest = AssetRequest::create([
                'status' => $isRequesterApprover ? 'For Approval of Approver ' . ($requesterLayer + 1) : 'For Approval of Approver 1',
                'requester_id' => $requesterId,
                'transaction_number' => $transactionNumber,
                'reference_number' => (new AssetRequest)->generateReferenceNumber(),
                'type_of_request_id' => $request['type_of_request_id'],
                'attachment_type' => $request['attachment_type'],
                'charged_department_id' => $request['charged_department_id'],
                'subunit_id' => $request['subunit_id'],
                'accountability' => $request['accountability'],
                'accountable' => $request['accountable'] ?? null,
                'asset_description' => $request['asset_description'],
                'asset_specification' => $request['asset_specification'] ?? null,
                'cellphone_number' => $request['cellphone_number'] ?? null,
                'brand' => $request['brand'] ?? null,
                'quantity' => $request['quantity'],
            ]);

            $fileKeys = ['letter_of_request', 'quotation', 'specification_form', 'tool_of_trade', 'other_attachments'];

            foreach($fileKeys as $fileKey) {
                if(isset($request[$fileKey])) {
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

        $assetRequest = AssetRequest::where('transaction_number', $transactionNumber)
            ->where('requester_id', $requestorId)
            ->get();

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
}

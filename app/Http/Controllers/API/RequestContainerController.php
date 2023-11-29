<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\RequestContainer\CreateRequestContainerRequest;
use App\Http\Requests\RequestContainer\UpdateRequestContainerRequest;
use App\Models\AssetRequest;
use App\Models\Department;
use App\Models\DepartmentUnitApprovers;
use App\Models\RequestContainer;
use App\Models\SubUnit;
use App\Traits\AssetRequestHandler;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class RequestContainerController extends Controller
{
    use ApiResponse, AssetRequestHandler;


    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $requesterId = auth('sanctum')->user()->id;
        $requestContainer = RequestContainer::where('requester_id', $requesterId)
//            ->orderBy('created_at', 'desc')
            ->useFilters()
            ->get();

        return $this->transformShowAssetRequest($requestContainer);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(CreateRequestContainerRequest $request)
    {
//        return $request->all();
        $requesterId = auth('sanctum')->user()->id;
//        $transactionNumber = RequestContainer::generateTransactionNumber($requesterId);
        $departmentUnitApprovers = DepartmentUnitApprovers::with('approver')->where('subunit_id', $request->subunit_id)
            ->orderBy('layer', 'asc')
            ->get();

        $layerIds = $departmentUnitApprovers->map(function ($approverObject) {
            return $approverObject->approver->approver_id;
        })->toArray();

        $isRequesterApprover = in_array($requesterId, $layerIds);
        $isLastApprover = false;
        if ($isRequesterApprover) {
            $requesterLayer = array_search($requesterId, $layerIds) + 1;
            // Get the maximum (last) layer
            $maxLayer = $departmentUnitApprovers->max('layer');

            // Check if reqesuter is the last approver
            $isLastApprover = $maxLayer == $requesterLayer;
        }

        $assetRequest = RequestContainer::create([
            'status' => $isLastApprover
                ? 'Approved'
                : ($isRequesterApprover
                    ? 'For Approval of Approver ' . ($requesterLayer + 1)
                    : 'For Approval of Approver 1'),
            'requester_id' => $requesterId,
            'type_of_request_id' => $request->type_of_request_id,
            'attachment_type' => $request->attachment_type,
            'subunit_id' => $request->subunit_id,
            'location_id' => $request->location_id,
            'account_title_id' => $request->account_title_id,
            'accountability' => $request->accountability,
            'company_id' => $request->company_id,
            //Department::find($request->department_id)->company->id,
            'department_id' => $request->department_id,
            'accountable' => $request->accountable ?? null,
            'asset_description' => $request->asset_description,
            'asset_specification' => $request->asset_specification ?? null,
            'cellphone_number' => $request->cellphone_number ?? null,
            'brand' => $request->brand ?? null,
            'quantity' => $request->quantity,
        ]);

        $fileKeys = ['letter_of_request', 'quotation', 'specification_form', 'tool_of_trade', 'other_attachments'];

        foreach ($fileKeys as $fileKey) {
            if (isset($request->$fileKey)) {
                $files = is_array($request->$fileKey) ? $request->$fileKey : [$request->$fileKey];
                foreach ($files as $file) {
                    $assetRequest->addMedia($file)->toMediaCollection($fileKey);
                }
            }
        }

        return $this->responseCreated('Request Container Created', $assetRequest);
    }


    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $requestContainer = RequestContainer::find($id);
        if (!$requestContainer) {
            return $this->responseNotFound('Request Not Found');
        }

        return $this->transformForSingleItemOnly($requestContainer);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateRequestContainerRequest $request, $id)
    {

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int|null $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function removeAll(int $id = null): \Illuminate\Http\JsonResponse
    {
        //if the $id is not null, then delete the specific request container
        if ($id) {
            $requestContainer = RequestContainer::find($id);
            $requestContainer->clearMediaCollection(); // remove all media
            $requestContainer->delete();               // delete the container
            return $this->responseSuccess('Item Deleted');
        } else {
            $requestorId = auth('sanctum')->user()->id;
            $requestContainers = RequestContainer::where('requester_id', $requestorId)->get();

            foreach ($requestContainers as $requestContainer) {
                $requestContainer->clearMediaCollection(); // remove all media
                $requestContainer->delete();               // delete the container
            }
            return $this->responseSuccess('Request Deleted');
        }
    }

    public function updateContainer(UpdateRequestContainerRequest $request, $id): \Illuminate\Http\JsonResponse
    {
        $requestContainer = RequestContainer::find($id);
        if (!$requestContainer) {
            return $this->responseNotFound('Request Not Found');
        }

        $requestContainer->update([
            'type_of_request_id' => $request->type_of_request_id,
            'attachment_type' => $request->attachment_type,
//            'subunit_id' => $request->subunit_id['id'],
//            'location_id' => $request->location_id['id'],
//            'account_title_id' => $request->account_title_id['id'],
            'accountability' => $request->accountability,
//            'company_id' => $request->department_id['company']['company_id'],
//            'department_id' => $request->department_id['id'],
            'accountable' => $request->accountable ?? null,
            'asset_description' => $request->asset_description,
            'asset_specification' => $request->asset_specification ?? null,
            'cellphone_number' => $request->cellphone_number ?? null,
            'brand' => $request->brand ?? null,
            'quantity' => $request->quantity ?? 1,
        ]);

        if ($requestContainer) {
            $this->handleMediaAttachments($requestContainer, $request);
        }

        return $this->responseSuccess('Request updated Successfully');
    }
}

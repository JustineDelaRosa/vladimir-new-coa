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
use App\Traits\RequestContainerHandler;
use App\Traits\RequestShowDataHandler;
use Essa\APIToolKit\Api\ApiResponse;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class RequestContainerController extends Controller
{
    use ApiResponse, AssetRequestHandler, RequestShowDataHandler, RequestContainerHandler;

    public function index()
    {
        $requesterId = auth('sanctum')->user()->id;
        $requestContainer = RequestContainer::where('requester_id', $requesterId)
            //            ->orderBy('created_at', 'desc')
            ->useFilters()
            ->dynamicPaginate();

        return $this->responseData($requestContainer);
    }

    public function store(CreateRequestContainerRequest $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $requesterId = auth('sanctum')->user()->id;
            $departmentUnitApprovers = DepartmentUnitApprovers::with('approver')->where('subunit_id', $request->subunit_id)
                ->orderBy('layer', 'asc')
                ->get();

            list($isRequesterApprover, $isLastApprover, $requesterLayer) = $this->checkIfRequesterIsApprover($requesterId, $departmentUnitApprovers);
//            return $isRequesterApprover;
            $this->checkDifferentCOA($request);

            $assetRequest = $this->createRequestContainer($request, $isRequesterApprover, $isLastApprover, $requesterLayer, $requesterId);

            $this->addMediaToRequestContainer($request, $assetRequest);
//            return $assetRequest->status;
            $this->updateStatusIfDifferent($assetRequest->status);

            DB::commit();
            return $this->responseCreated('Request Container Created', $assetRequest);
        } catch (Exception $e) {
            DB::rollback();
            return $this->responseUnprocessable('Something went wrong. Please try again later.' . $e->getMessage());
        }
    }

    public function show($id)
    {
        $requestContainer = RequestContainer::find($id);
        if (!$requestContainer) {
            return $this->responseNotFound('Request Not Found');
        }

        return $this->transformForSingleItemOnly($requestContainer);
    }


    public function update(UpdateRequestContainerRequest $request, $id)
    {
    }

    public function removeAll(int $id = null): \Illuminate\Http\JsonResponse
    {
        //if the $id is not null, then delete the specific request container
        $fileKeys = ['letter_of_request', 'quotation', 'specification_form', 'tool_of_trade', 'other_attachments'];
        if ($id) {
            $requestContainer = RequestContainer::find($id);
            foreach ($fileKeys as $fileKey) {
                $requestContainer->clearMediaCollection($fileKey);
            }
            $requestContainer->forceDelete();             // delete the container
            return $this->responseSuccess('Item Deleted');
        } else {
            $requestorId = auth('sanctum')->user()->id;
            $requestContainers = RequestContainer::where('requester_id', $requestorId)->get();

            foreach ($requestContainers as $requestContainer) {
                $requestContainer->clearMediaCollection(); // remove all media
                foreach ($fileKeys as $fileKey) {
                    $requestContainer->clearMediaCollection($fileKey);
                }
                $requestContainer->forceDelete();        // delete the container
            }
            return $this->responseSuccess('Request Deleted');
        }
    }

    public function updateContainer(UpdateRequestContainerRequest $request, $id)
    {

        $requestContainer = RequestContainer::find($id);
        if (!$requestContainer) {
            return $this->responseNotFound('Request Not Found');
        }
        $this->checkDifferentCOA($request);
        $requestContainer->update([
            'fixed_asset_id' => $request->fixed_asset_id ?? null,
            'type_of_request_id' => $request->type_of_request_id,
            'capex_number' => $request->capex_number,
            'attachment_type' => $request->attachment_type,
            'company_id' => $request->company_id,
            'business_unit_id' => $request->business_unit_id,
            'department_id' => $request->department_id,
            'unit_id' => $request->unit_id,
            'subunit_id' => $request->subunit_id,
            'location_id' => $request->location_id,
            'account_title_id' => $request->account_title_id,
            'accountability' => $request->accountability,
            'additional_info' => $request->additional_info ?? null,
            'acquisition_details' => $request->acquisition_details,
            'accountable' => $request->accountable ?? null,
            'asset_description' => $request->asset_description,
            'asset_specification' => $request->asset_specification ?? null,
            'cellphone_number' => $request->cellphone_number ?? null,
            'brand' => $request->brand ?? null,
            'quantity' => $request->quantity ?? 1,
            'uom_id' => $request->uom_id,
            'date_needed' => $request->date_needed,
        ]);

        if ($requestContainer) {
            $this->handleMediaAttachments($requestContainer, $request);
        }

        return $this->responseSuccess('Request updated Successfully');
    }
}

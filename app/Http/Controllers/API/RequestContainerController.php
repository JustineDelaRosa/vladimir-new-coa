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
use App\Traits\RequestShowDataHandler;
use Essa\APIToolKit\Api\ApiResponse;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class RequestContainerController extends Controller
{
    use ApiResponse, AssetRequestHandler, RequestShowDataHandler;


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
            ->dynamicPaginate();

        return $this->responseData($requestContainer);
    }

    public function store(CreateRequestContainerRequest $request)
    {
        DB::beginTransaction();
        try {
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
            $this->checkDifferentCOA($request);

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
                'additional_info' => $request->additional_info ?? null,
                'acquisition_details' => $request->acquisition_details,
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
            DB::commit();
            return $this->responseCreated('Request Container Created', $assetRequest);
        } catch (Exception $e) {
            DB::rollback();
            return $this->responseUnprocessable('Failed to create RequestContainer');
        }
    }

    private function checkDifferentCOA($request)
    {
        $requesterId = auth('sanctum')->user()->id;
        $requestContainer = RequestContainer::where('requester_id', $requesterId)->get();
        if ($requestContainer->isNotEmpty()) {
            //check if the this and the subunit is the same on the request container
            if ($requestContainer->first()->subunit_id != $request->subunit_id) {
                foreach ($requestContainer as $requestContainerItem)
                    $requestContainerItem->update([
                        'company_id' => $request->company_id,
                        'department_id' => $request->department_id,
                        'subunit_id' => $request->subunit_id,
                        'location_id' => $request->location_id,
//                'account_title_id' => $request->account_title_id,
                        'acquisition_details' => $request->acquisition_details ?? null,

                    ]);
            }
        }
        return;
    }

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

    public function removeAll(int $id = null): \Illuminate\Http\JsonResponse
    {
        //if the $id is not null, then delete the specific request container
        $fileKeys = ['letter_of_request', 'quotation', 'specification_form', 'tool_of_trade', 'other_attachments'];
        if ($id) {
            $requestContainer = RequestContainer::find($id);
            foreach ($fileKeys as $fileKey) {
                $requestContainer->clearMediaCollection($fileKey);
            }
            $requestContainer->delete();               // delete the container
            return $this->responseSuccess('Item Deleted');
        } else {
            $requestorId = auth('sanctum')->user()->id;
            $requestContainers = RequestContainer::where('requester_id', $requestorId)->get();

            foreach ($requestContainers as $requestContainer) {
                $requestContainer->clearMediaCollection(); // remove all media
                foreach ($fileKeys as $fileKey) {
                    $requestContainer->clearMediaCollection($fileKey);
                }
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
            'additional_info' => $request->additional_info ?? null,
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

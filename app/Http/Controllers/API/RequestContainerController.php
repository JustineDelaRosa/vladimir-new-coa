<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\RequestContainer\CreateRequestContainerRequest;
use App\Http\Requests\RequestContainer\UpdateRequestContainerRequest;
use App\Models\AssetRequest;
use App\Models\Department;
use App\Models\DepartmentUnitApprovers;
use App\Models\Location;
use App\Models\RequestContainer;
use App\Models\SubUnit;
use App\Rules\NewCoaValidation\BusinessUnitValidation;
use App\Rules\NewCoaValidation\DepartmentValidation;
use App\Rules\NewCoaValidation\LocationValidation;
use App\Rules\NewCoaValidation\SubunitValidation;
use App\Rules\NewCoaValidation\UnitValidation;
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

    public function store(CreateRequestContainerRequest $request)
    {

        DB::beginTransaction();
        try {
//            $request->initial_debit_id = 5;
//            $request->depreciation_credit_id = 5;
            $user = auth('sanctum')->user();
            $requesterId = $user->id;

            $request->merge([
                'receiving_warehouse_id' => Department::where('id', $request->department_id)->pluck('receiving_warehouse_id')->first(),
                'is_addcost' => $request->item_id ? 1 : 0,
            ]);

            $departmentUnitApprovers = DepartmentUnitApprovers::with('approver')->where('subunit_id', $request->subunit_id)
                ->orderBy('layer', 'asc')
                ->get();

//            return $request->all();
            list($isRequesterApprover, $isLastApprover, $requesterLayer) = $this->checkIfRequesterIsApprover($requesterId, $departmentUnitApprovers);
//            return $departmentUnitApprovers;

            $this->checkDifferentCOA($request);
            $accountingEntries = $this->creatAccountingEntries(
                $request->initial_debit_id,
                $request->depreciation_credit_id,
                $request,
                $isRequesterApprover,
                $isLastApprover,
                $requesterLayer,
                $requesterId
            );

            DB::commit();
            return $this->responseCreated('Added successfully', $accountingEntries);
        } catch (Exception $e) {
            DB::rollback();
            return $e;
            return $this->responseUnprocessable('Something went wrong. Please try again later.');
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

    public function removeAll(int $id = null)
    {
        DB::beginTransaction();
        try {
            $fileKeys = ['letter_of_request', 'quotation', 'specification_form', 'tool_of_trade', 'other_attachments'];
            if ($id) {
                $requestContainer = RequestContainer::find($id);
                if ($requestContainer) {

                    foreach ($fileKeys as $fileKey) {
                        $requestContainer->clearMediaCollection($fileKey);
                    }
                    $requestContainer->forceDelete();
                    $requestContainer->accountingEntries()->delete();
                }
            } else {
                $requestorId = auth('sanctum')->user()->id;
                $requestContainers = RequestContainer::where('requester_id', $requestorId)->get();
                foreach ($requestContainers as $requestContainer) {

                    $requestContainer->clearMediaCollection();
                    foreach ($fileKeys as $fileKey) {
                        $requestContainer->clearMediaCollection($fileKey);
                    }
                    $requestContainer->forceDelete();
                    $requestContainer->accountingEntries()->delete();
                }
            }
            DB::commit();
            return $this->responseSuccess('Request Deleted');
        } catch (Exception $e) {
            DB::rollback();
//            return $e->getMessage();
            return $this->responseUnprocessable('Something went wrong. Please try again later.');
        }
    }


    public function updateContainer(UpdateRequestContainerRequest $request, $id)
    {
        DB::beginTransaction();
        try {

//            return $request->initail_debit_id . ' ' . $request->depreciation_credit_id;
            $requestContainer = RequestContainer::find($id);
            if (!$requestContainer) {
                return $this->responseNotFound('Request Not Found');
            }
            $request->merge([
                'receiving_warehouse_id' => Department::where('id', $request->department_id)->pluck('receiving_warehouse_id')->first(),
            ]);
            $this->checkDifferentCOA($request);


            $requestContainer->update([
                'fixed_asset_id' => $request->fixed_asset_id ?? null,
                'type_of_request_id' => $request->type_of_request_id,
                'capex_number' => $request->capex_number,
                'attachment_type' => $request->attachment_type,
                'one_charging_id' => $request->one_charging_id,
                'company_id' => $request->company_id,
                'item_id' => $request->item_id ?? null,
                'is_addcost' => $request->item_id ? 1 : 0,
                'business_unit_id' => $request->business_unit_id,
                'department_id' => $request->department_id,
                'unit_id' => $request->unit_id,
                'subunit_id' => $request->subunit_id,
                'location_id' => $request->location_id,
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
                'item_status' => $request->item_status,
            ]);

            $requestContainer->accountingEntries()->update([
                'initial_debit' => $request->initial_debit_id,
                'depreciation_credit' => $request->depreciation_credit_id,
            ]);

            $this->handleMediaAttachments($requestContainer, $request);

            DB::commit();
            return $this->responseSuccess('Request updated Successfully');
        } catch (Exception $e) {
            DB::rollback();
//            return $e->getFile() . ' ' . $e->getLine() . ' ' . $e->getMessage();
            return $this->responseUnprocessable('Something went wrong. Please try again later.');
        }
    }
}

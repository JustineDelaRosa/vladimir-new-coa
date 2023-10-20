<?php

namespace App\Http\Controllers\Masterlist\COA;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubUnit\CreateSubUnitRequest;
use App\Http\Requests\SubUnit\UpdateSubUnitRequest;
use App\Models\SubUnit;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubUnitController extends Controller
{

    use ApiResponse;

    public function index(Request $request)
    {
        $requestStatus = $request->status;
        $isActiveStatus = ($requestStatus === "deactivated") ? 0 : 1;

        $subUnits = SubUnit::withTrashed()->where('is_active', $isActiveStatus)->useFilters()->dynamicPaginate();

        $subUnits->transform(function ($subUnit) {
           return[
               'id' => $subUnit->id,
               'subunit_name' => $subUnit->sub_unit_name,
               'status' => $subUnit->is_active ,
               'department' => $subUnit->department->department_name ?? '-',
           ];
        });

        return $subUnits;
    }

    public function store(CreateSubUnitRequest $request): JsonResponse
    {
        $subUnit = SubUnit::create($request->all());

        return $this->responseCreated('SubUnit created successfully', $subUnit);
    }

    public function show(SubUnit $subUnit): JsonResponse
    {
        return $this->responseSuccess('SubUnit retrieved successfully', $subUnit);
    }

    public function update(UpdateSubUnitRequest $request, SubUnit $subUnit): JsonResponse
    {
        $subUnit->update($request->all());

        return $this->responseSuccess('SubUnit updated Successfully', $subUnit);
    }

    public function archived(CreateSubUnitRequest $request, $id)
    {
        $status = $request->status;
        $subUnit = SubUnit::query();
        if (!$subUnit->withTrashed()->where('id', $id)->exists()) {
            return $this->responseNotFound('SubUnit not found');
        }

        if ($status == false) {
            if (!$subUnit->clone()->where('id', $id)->where('is_active', true)->exists()) {
                return $this->responseSuccess('No changes');
            } else {
                $removeSubUnit = SubUnit::archive($id);
                return $this->responseSuccess('SubUnit archived successfully', $removeSubUnit);
            }
        }

        if ($subUnit == true) {
            if ($subUnit->clone()->where('id', $id)->where('is_active', true)->exists()) {
                return $this->responseSuccess('No changes');
            } else {
                $restoreSubUnit = SubUnit::restoreSubUnit($id);
                return $this->responseSuccess('SubUnit restored successfully', $restoreSubUnit);
            }
        }
    }
}

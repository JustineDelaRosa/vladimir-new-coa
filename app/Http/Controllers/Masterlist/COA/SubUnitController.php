<?php

namespace App\Http\Controllers\Masterlist\COA;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubUnit\CreateSubUnitRequest;
use App\Http\Requests\SubUnit\UpdateSubUnitRequest;
use App\Models\DepartmentUnitApprovers;
use App\Models\SubUnit;
use App\Models\User;
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

        $subUnits = SubUnit::withTrashed()->where('is_active', $isActiveStatus)
            ->orderBy('created_at', 'desc')
            ->useFilters()
            ->dynamicPaginate();

        $subUnits->transform(function ($subUnit) {
            return [
                'id' => $subUnit->id,
                'subunit_code' => $subUnit->sub_unit_code ?? '-',
                'subunit_name' => $subUnit->sub_unit_name,
                'is_active' => $subUnit->is_active,
                'department' => [
                    'id' => $subUnit->department->id,
                    'department_code' => $subUnit->department->department_code,
                    'department_name' => $subUnit->department->department_name,
                ],
                'tagged' => $subUnit->departmentUnitApprovers()->exists(),
            ];
        });

        return $subUnits;
    }

    public function store(CreateSubUnitRequest $request): JsonResponse
    {
        $request->validated();
        $departmentId = $request->department_id;
        $subUnitName = $request->subunit_name;

        $subUnit = SubUnit::create([
            'sub_unit_code' => SubUnit::generateCode(),
            'department_id' => $departmentId,
            'sub_unit_name' => $subUnitName,
        ]);

        return $this->responseCreated('SubUnit created successfully', $subUnit);
    }

    public function show(SubUnit $subUnit): JsonResponse
    {
        return $this->responseSuccess('SubUnit retrieved successfully', $subUnit);
    }

    public function update(UpdateSubUnitRequest $request, $id): JsonResponse
    {
        $subUnit = SubUnit::find($id);

        if(!$subUnit) {
            return $this->responseNotFound('SubUnit not found');
        }
        //sub unit is tagged
        if($subUnit->departmentUnitApprovers()->exists()) {
            return $this->responseUnprocessable('You cannot update a tagged sub unit');
        }

        $departmentId = $request->department_id;
        $subUnitName = $request->subunit_name;

        $subUnitUpdate = SubUnit::where('id', $id)->update([
            'department_id' => $departmentId,
            'sub_unit_name' => $subUnitName,
        ]);

        return $this->responseSuccess('SubUnit updated Successfully');
    }

    /**
     * Archive or restore a subunit based on the provided status.
     *
     * @param CreateSubUnitRequest $request The request data.
     * @param int $id The ID of the subunit to archive or restore.
     * @return mixed The response data.
     */
    public function archived(CreateSubUnitRequest $request, int $id)
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
                $departmentUnitApprovers = DepartmentUnitApprovers::where('subunit_id', $id)->exists();
                $users = User::where('subunit_id', $id)->exists();

                if($departmentUnitApprovers) {
                    return $this->responseUnprocessable('You cannot archive a tagged sub unit');
                }
                if($users) {
                    return $this->responseUnprocessable('You cannot archive a sub unit with users');
                }
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

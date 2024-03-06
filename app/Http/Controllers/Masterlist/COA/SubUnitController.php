<?php

namespace App\Http\Controllers\Masterlist\COA;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubUnit\CreateSubUnitRequest;
use App\Http\Requests\SubUnit\UpdateSubUnitRequest;
use App\Models\DepartmentUnitApprovers;
use App\Models\SubUnit;
use App\Models\Unit;
use App\Models\User;
use App\Traits\COA\SubUnitHandler;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubUnitController extends Controller
{

    use ApiResponse, SubUnitHandler;

    public function index(Request $request)
    {
        $requestStatus = $request->status;
        $isActiveStatus = ($requestStatus === "deactivated") ? 0 : 1;

        $subUnits = SubUnit::withTrashed()->where('is_active', $isActiveStatus)
            ->orderBy('created_at', 'desc')
            ->useFilters()
            ->dynamicPaginate();

        return $this->transformSubunit($subUnits);
    }

    public function store(Request $request)
    {
        $unit = Unit::all()->isEmpty();
        if ($unit) {
            return $this->responseUnprocessable('Unit Data not Ready');
        }

        $subUnit = $request->input('result');
        if (empty($request->all()) || empty($request->input('result'))) {
            return $this->responseUnprocessable('Data not Ready');
        }

        foreach($subUnit as $subUnits) {
            $sync_id = $subUnits['id'];
            $code = $subUnits['code'];
            $unit_sync_id = $subUnits['department_unit']['id'];
            $name = $subUnits['name'];
            $is_active = $subUnits['deleted_at'];

            $sync = SubUnit::updateOrCreate(
                [
                    'sync_id' => $sync_id,
                ],
                [
                    'sub_unit_code' => $code,
                    'sub_unit_name' => $name,
                    'unit_sync_id' => $unit_sync_id,
                    'is_active' => $is_active == NULL ? 1 : 0,
                ]
            );
        }
        return $this->responseSuccess('Successfully Synced!');
    }

    public function show(SubUnit $subUnit): JsonResponse
    {
        return $this->responseSuccess('Subunit retrieved successfully', $subUnit);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $subUnit = SubUnit::find($id);

        if(!$subUnit) {
            return $this->responseNotFound('Subunit not found');
        }
        //sub unit is tagged
        if($subUnit->departmentUnitApprovers()->exists()) {
            return $this->responseUnprocessable('You cannot update a tagged subunit');
        }

        $departmentId = $request->department_id;
        $subUnitName = $request->subunit_name;

        $subUnitUpdate = SubUnit::where('id', $id)->update([
            'department_id' => $departmentId,
            'sub_unit_name' => $subUnitName,
        ]);

        return $this->responseSuccess('Subunit updated Successfully');
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
            return $this->responseNotFound('Subunit not found');
        }
        if ($status == false) {
            if (!$subUnit->clone()->where('id', $id)->where('is_active', true)->exists()) {
                return $this->responseSuccess('No changes');
            } else {
                $departmentUnitApprovers = DepartmentUnitApprovers::where('subunit_id', $id)->exists();
                $users = User::where('subunit_id', $id)->exists();

                if($departmentUnitApprovers) {
                    return $this->responseUnprocessable('You cannot archive a tagged subunit');
                }
                if($users) {
                    return $this->responseUnprocessable('You cannot archive a subunit with users');
                }
                $removeSubUnit = SubUnit::archive($id);
                return $this->responseSuccess('Subunit archived successfully', $removeSubUnit);
            }
        }

        if ($subUnit == true) {
            if ($subUnit->clone()->where('id', $id)->where('is_active', true)->exists()) {
                return $this->responseSuccess('No changes');
            } else {
                $restoreSubUnit = SubUnit::restoreSubUnit($id);
                return $this->responseSuccess('Subunit restored successfully', $restoreSubUnit);
            }
        }
    }
}

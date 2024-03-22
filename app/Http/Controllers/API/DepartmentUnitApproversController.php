<?php

namespace App\Http\Controllers\API;

use App\Models\DepartmentUnitApprovers;
use App\Models\SubUnit;
use App\Models\Unit;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;

use App\Http\Requests\DepartmentUnitApprovers\CreateDepartmentUnitApproversRequest;
use App\Http\Requests\DepartmentUnitApprovers\UpdateDepartmentUnitApproversRequest;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

class  DepartmentUnitApproversController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $perPage = $request->input('per_page', null);

        $transformedResults = DepartmentUnitApprovers::useFilters()->orderByDesc('created_at')->get()->groupBy('subunit_id')->map(function ($item) {
            return [
                /*'department_id' => $item[0]->department_id,
                'department_name' => $item[0]->department->department_name,
                'department_code' => $item[0]->department->department_code,*/
                'unit' => [
                    'id' => $item[0]->unit_id,
                    'unit_name' => $item[0]->unit->unit_name,
                    'unit_code' => $item[0]->unit->unit_code,
                ],
                'created_at' => $item[0]->created_at,
                'subunit' => [
                    'id' => $item[0]->subunit_id,
                    'subunit_code' => $item[0]->subUnit->sub_unit_code,
                    'subunit_name' => $item[0]->subUnit->sub_unit_name,
                ],
                'approvers' => $item->map(function ($item) {
                    return [
                        'approver_id' => $item->approver->id ?? '-',
                        'username' => $item->approver->user->username ?? '-',
                        'employee_id' => $item->approver->user->employee_id ?? '-',
                        'first_name' => $item->approver->user->firstname ?? '-',
                        'last_name' => $item->approver->user->lastname ?? '-',
                        'layer' => $item->layer ?? '-',
                    ];
                })->sortBy('layer')->values(),
            ];
        })->values();

        if ($perPage !== null) {
            $page = $request->input('page', 1);
            $offset = ($page * $perPage) - $perPage;
            $transformedResults = new LengthAwarePaginator(
                $transformedResults->slice($offset, $perPage)->values(),
                $transformedResults->count(),
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        }
        return $transformedResults;
    }

    public function store(CreateDepartmentUnitApproversRequest $request): JsonResponse
    {
        $subunitId = $request->subunit_id;
        $approverId = $request->approver_id;

        foreach ($approverId as $key => $approverIds) {
            $layer = DepartmentUnitApprovers::where('subunit_id', $subunitId)->max('layer');
            DepartmentUnitApprovers::create([
                'unit_id' => SubUnit::where('id', $subunitId)->first()->unit->id,
                'subunit_id' => $subunitId,
                'approver_id' => $approverIds,
                'layer' => $layer + 1,
            ]);
        }
        return $this->responseCreated('Created successfully');
    }

    public function show(DepartmentUnitApprovers $departmentUnitApprovers): JsonResponse
    {
        return $this->responseSuccess(null, $departmentUnitApprovers);
    }

    public function update(UpdateDepartmentUnitApproversRequest $request, $id): JsonResponse
    {

        return $this->responseSuccess('DepartmentUnitApprovers updated Successfully');
    }

    public function destroy($subUnitId): JsonResponse
    {
        //delete all with subunit id
        DepartmentUnitApprovers::where('subunit_id', $subUnitId)->delete();

        return $this->responseSuccess('Deleted Successfully');
    }


    public function arrangeLayer(UpdateDepartmentUnitApproversRequest $request, $id): JsonResponse
    {
        //        $unitId = $request->department_id;
        $subunitId = $id;
        $approverId = $request->approver_id;
        $unitId = SubUnit::where('id', $subunitId)->first()->unit_id;
        $layer = 1;

        $approverIds = DepartmentUnitApprovers::where('department_id', $unitId)
            ->where('subunit_id', $subunitId)
            ->pluck('approver_id')->toArray();


        $deletableApproverIds = array_diff($approverIds, $approverId);
        if (count($deletableApproverIds) > 0) {
            DepartmentUnitApprovers::where('unit_id', $unitId)
                ->where('subunit_id', $subunitId)
                ->whereIn('approver_id', $deletableApproverIds)->delete();
        }

        foreach ($approverId as $approver) {
            DepartmentUnitApprovers::updateOrCreate(
                [
                    'unit_id' => $unitId,
                    'subunit_id' => $subunitId,
                    'approver_id' => $approver,
                ],
                ['layer' => $layer++]
            );
        }
        return $this->responseSuccess('Unit Approvers updated Successfully');
    }
}

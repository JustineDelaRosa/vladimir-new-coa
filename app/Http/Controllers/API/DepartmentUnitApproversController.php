<?php

namespace App\Http\Controllers\API;

use App\Models\DepartmentUnitApprovers;
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

        $search = $request->input('search', '');
        $perPage = $request->input('per_page', null);

        $departmentUnitApprovers = DepartmentUnitApprovers::where(function ($query) use($search){
           $query->whereHas('department', function ($query) use($search){
               $query->where('department_name', 'LIKE', "%{$search}%");
           })->orWhereHas('subUnit', function ($query) use($search){
               $query->where('sub_unit_name', 'LIKE', "%{$search}%");
//           })->orWhereHas('approver.user', function ($query) use($search){
//               $query->where('firstname', 'LIKE', "%{$search}%")
//                     ->orWhere('lastname', 'LIKE', "%{$search}%")
//                     ->orWhere('username', 'LIKE', "%{$search}%")
//                     ->orWhere('employee_id', 'LIKE', "%{$search}%");
           });
        });


        $transformedResults = $departmentUnitApprovers->get()->groupBy('department_id')->map(function ($item) {
            return [
                'department_id' => $item[0]->department_id,
                'department_name' => $item[0]->department->department_name,
                'subunit' => $item->map(function ($item) {
                    return [
                        'subunit_id' => $item->subunit_id,
                        'subunit_name' => $item->subUnit->sub_unit_name,
                        'layer' => $item->layer,
                        'approver' => $item->approver->user,
                    ];
                })->groupBy('subunit_name')->sortBy('layer'),
            ];
        })->values();

        if($perPage !== null){
            $page = $request->input('page', 1);
            $offset = ($page * $perPage) - $perPage;
            $transformedResults = new LengthAwarePaginator(
                $transformedResults->slice($offset, $perPage),
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
        $departmentId = $request->department_id;
        $subunitId = $request->subunit_id;
        $approverId = $request->approver_id;

        foreach ($approverId as $key => $approverIds) {
            $layer = DepartmentUnitApprovers::where('department_id', $departmentId)
                ->where('subunit_id', $subunitId)->max('layer');
            DepartmentUnitApprovers::create([
                'department_id' => $departmentId,
                'subunit_id' => $subunitId,
                'approver_id' => $approverIds,
                'layer' => $layer + 1,
            ]);
        }
        return $this->responseCreated('DepartmentUnitApprovers created successfully');
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

        return $this->responseDeleted();
    }


    public function arrangeLayer(UpdateDepartmentUnitApproversRequest $request): JsonResponse
    {
        $departmentId = $request->department_id;
        $subunitId = $request->subunit_id;
        $approverId = $request->approver_id;
        $layer = 1;

        $approverIds = DepartmentUnitApprovers::where('department_id', $departmentId)
            ->where('subunit_id', $subunitId)
            ->pluck('approver_id')->toArray();


        $deletableApproverIds = array_diff($approverIds, $approverId);
        if (count($deletableApproverIds) > 0) {
            DepartmentUnitApprovers::where('department_id', $departmentId)
                ->where('subunit_id', $subunitId)
                ->whereIn('approver_id', $deletableApproverIds)->delete();
        }

        foreach ($approverId as $approver) {
            DepartmentUnitApprovers::updateOrCreate(
                [
                    'department_id' => $departmentId,
                    'subunit_id' => $subunitId,
                    'approver_id' => $approver,
                ],
                ['layer' => $layer++]
            );
        }
        return $this->responseSuccess('DepartmentUnitApprovers arranged successfully');
    }

}

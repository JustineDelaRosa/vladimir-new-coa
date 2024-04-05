<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\FormSetting\DepartmentUnitApprovers\CreateDepartmentUnitApproversRequest;
use App\Http\Requests\FormSetting\DepartmentUnitApprovers\UpdateDepartmentUnitApproversRequest;
use App\Models\DepartmentUnitApprovers;
use App\Traits\FormSettingHandler;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class  DepartmentUnitApproversController extends Controller
{
    use ApiResponse, FormSettingHandler;

    public function index(Request $request)
    {
        return $this->formSettingIndex($request, new DepartmentUnitApprovers);
    }

    public function store(CreateDepartmentUnitApproversRequest $request): JsonResponse
    {
        return $this->formSettingStore($request, new DepartmentUnitApprovers);
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
        return $this->formSettingDestroy(new DepartmentUnitApprovers, $subUnitId);
    }


    public function arrangeLayer(UpdateDepartmentUnitApproversRequest $request, $id)
    {
        return $this->formSettingArrangeLayer($request, new DepartmentUnitApprovers, $id);
    }
}

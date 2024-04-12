<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Controllers\UnitApproverBaseController;
use App\Http\Requests\FormSetting\DepartmentUnitApprovers\CreateDepartmentUnitApproversRequest;
use App\Http\Requests\FormSetting\DepartmentUnitApprovers\UpdateDepartmentUnitApproversRequest;
use App\Models\DepartmentUnitApprovers;
use App\Traits\FormSettingHandler;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class  DepartmentUnitApproversController extends UnitApproverBaseController
{
    public function __construct()
    {
        parent::__construct(
            new DepartmentUnitApprovers,
        );
    }
    protected function getCreateFormRequest()
    {
        return CreateDepartmentUnitApproversRequest::class;
    }
    protected function getUpdateFormRequest()
    {
        return UpdateDepartmentUnitApproversRequest::class;
    }
}

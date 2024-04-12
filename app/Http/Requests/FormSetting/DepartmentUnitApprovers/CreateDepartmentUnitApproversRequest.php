<?php

namespace App\Http\Requests\FormSetting\DepartmentUnitApprovers;

use App\Http\Requests\FormSettingBase\UnitApproverCreateBaseRequest;
use App\Models\DepartmentUnitApprovers;
use App\Rules\SubunitApproverExists;
use Illuminate\Foundation\Http\FormRequest;

class CreateDepartmentUnitApproversRequest extends UnitApproverCreateBaseRequest
{
    public function __construct()
    {
        parent::__construct(new DepartmentUnitApprovers);
    }

    public function authorize(): bool
    {
        return true;
    }
}
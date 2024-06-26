<?php

namespace App\Http\Requests\FormSetting\DepartmentUnitApprovers;

use App\Http\Requests\FormSettingBase\UnitApproverUpdateBaseRequest;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDepartmentUnitApproversRequest extends UnitApproverUpdateBaseRequest
{
    public function __construct()
    {
        parent::__construct();
    }

    public function authorize(): bool
    {
        return true;
    }
}

<?php

namespace App\Rules\ImportValidation;

use App\Models\BusinessUnit;
use App\Models\Department;
use Illuminate\Contracts\Validation\Rule;

class ValidDepartmentCode implements Rule
{
    private $businessUnitCode;
    private $departmentName;
    private string $errorMessage;

    public function __construct($businessUnitCode, $departmentName)
    {
        $this->businessUnitCode = $businessUnitCode;
        $this->departmentName = $departmentName;
    }

    public function passes($attribute, $value)
    {
        $inactive = Department::where('department_code', $value)->where('is_active', 0)->first();
        if ($inactive) {
            $this->errorMessage = 'Department is inactive';
            return false;
        }

        $department = Department::query()
            ->where('department_code', $value)
            ->where('department_name', $this->departmentName)
            ->where('is_active', '!=', 0)
            ->first();

        if (!$department) {
            $this->errorMessage = 'Department does not exist';
            return false;
        }

        $businessUnitSyncId = BusinessUnit::where('business_unit_code', $this->businessUnitCode)->first()->sync_id ?? 0;
        $departmentBUnitCheck = Department::where('department_code', $value)
            ->where('business_unit_sync_id', $businessUnitSyncId)
            ->first();

        if(!$departmentBUnitCheck) {
            $this->errorMessage = 'Department does not belong to the business unit';
            return false;
        }

        return (bool)$departmentBUnitCheck;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return $this->errorMessage;
    }
}

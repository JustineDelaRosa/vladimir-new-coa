<?php

namespace App\Rules\ImportValidation;

use App\Models\Department;
use App\Models\Unit;
use Illuminate\Contracts\Validation\Rule;

class ValidUnitCode implements Rule
{
    private $departmentCode;
    private $unitName;
    private string $errorMessage;

    public function __construct($departmentCode, $unitName)
    {
        $this->departmentCode = $departmentCode;
        $this->unitName = $unitName;
    }

    public function passes($attribute, $value)
    {
        $inactive = Unit::where('unit_code', $value)->where('is_active', 0)->first();
        if ($inactive) {
            $this->errorMessage = 'The unit is inactive';
            return false;
        }

        $unit = Unit::query()
            ->where('unit_code', $value)
            ->where('unit_name', $this->unitName)
            ->where('is_active', '!=', 0)
            ->first();

        if (!$unit) {
            $this->errorMessage = 'The unit does not exist';
            return false;
        }

        $departmentSyncId = Department::where('department_code', $this->departmentCode)->first()->sync_id ?? 0;
        $unitDepartmentCheck = Unit::where('unit_code', $value)
            ->where('department_sync_id', $departmentSyncId)
            ->first();
        if(!$unitDepartmentCheck) {
            $this->errorMessage = 'The unit does not belong to the department';
            return false;
        }

        return (bool)$unitDepartmentCheck;
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

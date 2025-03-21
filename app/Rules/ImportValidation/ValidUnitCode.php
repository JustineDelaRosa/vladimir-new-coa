<?php

namespace App\Rules\ImportValidation;

use App\Models\BusinessUnit;
use App\Models\Department;
use App\Models\Unit;
use Illuminate\Contracts\Validation\Rule;

class ValidUnitCode implements Rule
{
    private $departmentCode;
    private $businessUnitCode;
    private $unitName;
    private $errorMessage;

    public function __construct($departmentCode, $unitName, $businessUnitCode)
    {
        $this->departmentCode = $departmentCode;
        $this->unitName = $unitName;
        $this->businessUnitCode = $businessUnitCode;
    }

    public function passes($attribute, $value)
    {
        $inactive = Unit::where('unit_code', (string)$value)
            ->whereRaw('BINARY unit_code = ?', [(string)$value])
            ->where('is_active', 0)
            ->first();
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
        $businessUnitSyncId = BusinessUnit::where('business_unit_code', $this->businessUnitCode)->first()->sync_id ?? 0;
        $departmentSyncId = Department::where(['department_code' => $this->departmentCode, 'business_unit_sync_id' => $businessUnitSyncId])->first()->sync_id ?? 0;
        $unitDepartmentCheck = Unit::where('unit_code', $value)
            ->where('department_sync_id', $departmentSyncId)
            ->first();
        if (!$unitDepartmentCheck) {
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

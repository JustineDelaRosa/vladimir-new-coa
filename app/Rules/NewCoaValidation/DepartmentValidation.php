<?php

namespace App\Rules\NewCoaValidation;

use App\Models\BusinessUnit;
use App\Models\Department;
use Illuminate\Contracts\Validation\Rule;

class DepartmentValidation implements Rule
{
    private $businessUnitId;
    protected string $errorMessage;

    public function __construct($businessUnitId)
    {
        $this->businessUnitId = $businessUnitId;
    }


    public function passes($attribute, $value)
    {
        if(!$value) {
            return true;
        }
        $department = Department::query()->find($value);
        if (!$department || !$department->is_active) {
            $this->errorMessage = 'The department does not exist or is not active';
            return false;
        }

        $businessUnit = BusinessUnit::query()->with('departments')->findOrFail($this->businessUnitId);
        $businessUnit->departments->contains($department);
        if (!$businessUnit->departments->contains($department)) {
            $this->errorMessage = 'The department does not belong to the selected business unit';
            return false;
        }
        return true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message(): string
    {
        return $this->errorMessage;
    }
}

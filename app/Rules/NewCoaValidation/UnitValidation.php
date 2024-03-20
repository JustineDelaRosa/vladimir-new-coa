<?php

namespace App\Rules\NewCoaValidation;

use App\Models\Department;
use App\Models\Unit;
use Illuminate\Contracts\Validation\Rule;

class UnitValidation implements Rule
{
    private $departmentId;
    private string $errorMessage;

    public function __construct($departmentId)
    {
        $this->departmentId = $departmentId;
    }


    public function passes($attribute, $value)
    {
        $unit = Unit::query()->find($value);
        if (!$unit || !$unit->is_active) {
            $this->errorMessage = 'The unit does not exist or is not active';
            return false;
        }

        $department = Department::query()->with('unit')->where('id', $this->departmentId)->first();
        $unit = Unit::query()->where('id', $value)->first();
        if(!$department->unit->contains($unit)) {
            $this->errorMessage = 'The unit does not belong to the selected department';
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

<?php

namespace App\Rules\NewCoaValidation;

use App\Models\SubUnit;
use App\Models\Unit;
use Illuminate\Contracts\Validation\Rule;

class SubunitValidation implements Rule
{
    private $unitId;
    private string $errorMessage;
    public function __construct($unitId)
    {
        $this->unitId = $unitId;
    }

    public function passes($attribute, $value)
    {
        $subUnit = SubUnit::query()->find($value);
        if (!$subUnit || !$subUnit->is_active) {
            $this->errorMessage = 'The subunit does not exist or is not active';
            return false;
        }

        $unit = Unit::query()->with('subunits')->where('id', $this->unitId)->first();
        $subUnit = SubUnit::query()->where('id', $value)->first();
        if(!$unit->subunits->contains($subUnit)) {
            $this->errorMessage = 'The subunit does not belong to the selected unit';
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

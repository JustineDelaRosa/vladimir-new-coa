<?php

namespace App\Rules\NewCoaValidation;

use App\Models\Location;
use App\Models\SubUnit;
use Illuminate\Contracts\Validation\Rule;

class LocationValidation implements Rule
{
    private $subUnitId;
    private string $errorMessage;

    public function __construct($subUnitId)
    {
        $this->subUnitId = $subUnitId;
    }

    public function passes($attribute, $value)
    {
        if(!$value) {
            return true;
        }
        $location = Location::query()->find($value);
        if (!$location || !$location->is_active) {
            $this->errorMessage = 'The location does not exist or is not active';
            return false;
        }

        $subUnit = SubUnit::query()->with('location')->where('id', $this->subUnitId)->first();
        $location = Location::query()->where('id', $value)->first();
        if(!$subUnit->location->contains($location)) {
            $this->errorMessage = 'The location does not belong to the selected subunit';
            return false;
        }

        return true;
    }

    public function message(): string
    {
        return $this->errorMessage;
    }
}

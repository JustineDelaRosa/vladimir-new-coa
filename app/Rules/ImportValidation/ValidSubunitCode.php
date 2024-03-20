<?php

namespace App\Rules\ImportValidation;

use App\Models\SubUnit;
use App\Models\Unit;
use Illuminate\Contracts\Validation\Rule;

class ValidSubunitCode implements Rule
{
    private $unitCode;
    private $subunitName;
    private string $errorMessage;

    public function __construct($unitCode, $subunitName)
    {
        $this->unitCode = $unitCode;
        $this->subunitName = $subunitName;
    }


    public function passes($attribute, $value)
    {
        $inactive = SubUnit::where('sub_unit_code', $value)->where('is_active', 0)->first();
        if ($inactive) {
            $this->errorMessage = 'The subunit is inactive';
            return false;
        }

        $subunit = SubUnit::query()
            ->where('sub_unit_code', $value)
            ->where('sub_unit_name', $this->subunitName)
            ->where('is_active', '!=', 0)
            ->first();

        if (!$subunit) {
            $this->errorMessage = 'The subunit does not exist';
            return false;
        }

        $unitSyncId = Unit::where('unit_code', $this->unitCode)->first()->sync_id ?? 0;
        $subunitUnitCheck = SubUnit::where('sub_unit_code', $value)
            ->where('unit_sync_id', $unitSyncId)
            ->first();
        if(!$subunitUnitCheck) {
            $this->errorMessage = 'The subunit does not belong to the unit';
            return false;
        }

        return (bool)$subunitUnitCheck;
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

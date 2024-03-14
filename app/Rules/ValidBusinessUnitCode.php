<?php

namespace App\Rules;

use App\Models\BusinessUnit;
use App\Models\Company;
use Illuminate\Contracts\Validation\Rule;

class ValidBusinessUnitCode implements Rule
{
    private $companyCode;
    private string $errorMessage;
    private $businessUnitName;

    public function __construct($companyCode, $businessUnitName)
    {
        $this->companyCode = $companyCode;
        $this->businessUnitName = $businessUnitName;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $inactive = BusinessUnit::where('business_unit_code', $value)->where('is_active', 0)->first();
        if ($inactive) {
            $this->errorMessage = 'The business unit is inactive';
            return false;
        }

        $businessUnit = BusinessUnit::query()
            ->where('business_unit_code', $value)
            ->where('business_unit_name', $this->businessUnitName)
            ->where('is_active', '!=', 0)
            ->first();

        if (!$businessUnit) {
            $this->errorMessage = 'Invalid business unit';
            return false;
        }

        $companySyncId = Company::where('company_code', $this->companyCode)->first()->sync_id ?? 0;
        $businessUnitCompCheck = BusinessUnit::where('business_unit_code', $value)
            ->where('company_sync_id', $companySyncId)
            ->first();
        if(!$businessUnitCompCheck) {
            $this->errorMessage = 'Business unit does not belong to the company';
            return false;
        }

        return (bool)$businessUnit;
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

<?php

namespace App\Rules\NewCoaValidation;

use App\Models\BusinessUnit;
use App\Models\Company;
use App\Models\Location;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Bus;

class BusinessUnitValidation implements Rule
{
    private $company;
    private string $errorMessage;

    public function __construct($company)
    {
        $this->company = $company;
    }


    public function passes($attribute, $value)
    {
        if(!$value) {
            return true;
        }
        $businessUnit = BusinessUnit::query()->find($value);
        if (!$businessUnit || !$businessUnit->is_active) {
            $this->errorMessage = 'The business unit does not exist or is not active';
            return false;
        }

        $company = Company::where('id', $this->company)->first()->sync_id ?? 0;
        $businessUnitCompCheck = BusinessUnit::where('id', $value)
            ->where('company_sync_id', $company)
            ->first();
        if(!$businessUnitCompCheck) {
            $this->errorMessage = 'The business unit does not belong to the company';
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

<?php

namespace App\Rules;

use App\Models\Company;
use Illuminate\Contracts\Validation\Rule;

class ValidCompanyCode implements Rule
{
    private $companyName;
    private string $errorMessage;

    public function __construct($companyName)
    {
        $this->companyName = $companyName;
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
        $inactive = Company::where('company_code', $value)->where('is_active', 0)->first();
        if ($inactive) {
            $this->errorMessage = 'The company is inactive';
            return false;
        }

        $company = Company::query()
            ->where('company_code', $value)
            ->where('company_name', $this->companyName)
            ->where('is_active', '!=', 0)
            ->first();
        if(!$company) {
            $this->errorMessage = 'Invalid company';
            return false;
        }

        return (bool)$company;
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

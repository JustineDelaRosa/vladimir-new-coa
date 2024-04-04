<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class SubunitApproverExists implements Rule
{
    protected $model;
    protected string $errorMessage;

    public function __construct($model)
    {
        $this->model = $model;
    }


    public function passes($attribute, $value): bool
    {
        $exists = $this->model::where('subunit_id', $value)->exists();
        if($exists){
            $this->errorMessage = 'This Sub Unit is already have approvers.';
            return false;
        }
        return true;
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

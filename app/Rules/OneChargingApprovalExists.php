<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class OneChargingApprovalExists implements Rule
{

    protected $model;
    protected string $errorMessage;
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($model)
    {
        $this->model = $model;
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
        $exists = $this->model::where('one_charging_id', $value)->exists();
        if($exists){
            $this->errorMessage = 'This charging is already have approvers.';
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

<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class DuplicateHandle implements Rule
{
    protected $handles;

    public function __construct($handles)
    {
        $this->handles = $handles;
    }

    public function passes($attribute, $value)
    {
        $combinations = [];

        foreach ($this->handles as $handle) {
            $combination = implode('-', [
                $handle['company_id'],
                $handle['business_unit_id'],
                $handle['department_id'],
                $handle['unit_id'],
                $handle['subunit_id'],
                $handle['location_id'],
            ]);

            if (in_array($combination, $combinations)) {
                return false;
            }

            $combinations[] = $combination;
        }
        return true;
    }

    public function message()
    {
        return 'There are duplicate handles';
    }
}

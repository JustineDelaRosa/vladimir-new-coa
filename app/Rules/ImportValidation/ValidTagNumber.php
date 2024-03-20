<?php

namespace App\Rules\ImportValidation;

use App\Models\FixedAsset;
use Illuminate\Contracts\Validation\Rule;

class ValidTagNumber implements Rule
{
    private $collections;

    public function __construct($collections)
    {
        $this->collections = $collections;
    }

    public function passes($attribute, $value)
    {
        $duplicate = $this->collections->where('tag_number', $value)->where('tag_number', '!=', '-')->count();
        if ($duplicate > 1) {
            $this->errorMessage = 'Tag number in row ' . $attribute[0] . ' is not unique';
            return false;
        }

        $fixed_asset = FixedAsset::withTrashed()->where('tag_number', $value)->where('tag_number', '!=', '-')->first();
        if ($fixed_asset) {
            $this->errorMessage = 'Tag number already exists';
            return false;
        }

        return true;
    }

    public function message()
    {
        return $this->errorMessage;
    }
}
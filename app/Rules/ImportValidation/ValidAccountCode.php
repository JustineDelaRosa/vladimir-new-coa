<?php

namespace App\Rules\ImportValidation;

use App\Models\AccountTitle;
use Illuminate\Contracts\Validation\Rule;

class ValidAccountCode implements Rule
{
    private string $errorMessage;
    private $accountTitleName;

    public function __construct($accountTitleName)
    {
        $this->accountTitleName = $accountTitleName;
    }

    public function passes($attribute, $value)
    {
        $inactive = AccountTitle::where('account_title_code', $value)->where('is_active', 0)->first();
        if ($inactive) {
            $this->errorMessage = 'The account title is inactive';
            return false;
        }

        $accountTitle = AccountTitle::query()
            ->where('account_title_code', $value)
            ->where('account_title_name', $this->accountTitleName)
            ->where('is_active', '!=', 0)
            ->first();
        if(!$accountTitle){
            $this->errorMessage = 'The account title does not exist';
        }

        return (bool)$accountTitle;
    }

    public function message()
    {
        return $this->errorMessage;
    }
}

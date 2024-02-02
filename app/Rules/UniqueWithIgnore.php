<?php

namespace App\Rules;

use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Validation\Rule;

class UniqueWithIgnore implements Rule
{
    private $table;
    private $id;
    private $transactionNumber;

    public function __construct($table, $id, $transactionNumber)
    {
        $this->table = $table;
        $this->id = $id;
        $this->transactionNumber = $transactionNumber;
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
        return !DB::table($this->table)
            ->where($attribute, $value)
            ->where('id', '!=', $this->id)
            ->where('transaction_number', '!=', $this->transactionNumber)
            ->exists();
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'This ';
    }
}

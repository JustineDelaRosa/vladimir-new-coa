<?php

namespace App\Rules;

use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Validation\Rule;

class UniqueWithIgnore implements Rule
{
    private $table;
    private $id;
    private $transactionNumber;
    private string $errorMessage;
    private $supplier_id;

    public function __construct($table, $id, $transactionNumber, $supplier_id = null)
    {
        $this->table = $table;
        $this->id = $id;
        $this->transactionNumber = $transactionNumber;
        $this->supplier_id = $supplier_id;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $exists = DB::table($this->table)
            ->where($attribute, $value)
            ->where('id', '!=', $this->id)
            ->where('transaction_number', '!=', $this->transactionNumber)
            ->exists();

        if ($exists) {
            $this->errorMessage = 'The :attribute is already been taken by another transaction';
            return false;
        }

        $transaction = DB::table($this->table)
            ->where('transaction_number', $this->transactionNumber)
            ->where('po_number', $value)
            ->first();

        if (!$transaction) {
            return true;
        }

        if ($transaction->po_number == $value) {
            if ($this->supplier_id === null) {
                return true;
            }

            $supplier = DB::table('suppliers')
                ->where('id', $transaction->supplier_id)
                ->first();
            if ($supplier) {
                if ($supplier->id == $this->supplier_id) {
                    return true;
                } else {
                    $this->errorMessage = 'Different supplier for same PO on same transaction';
                    return false;
                }
            }
        }

        $this->errorMessage = 'The :attribute has already been used to a different transaction';
        return false;
    }

    public function message()
    {
        return $this->errorMessage;
    }


//    public function passes($attribute, $value)
//    {
//        // Check if any item with the same attribute value and different id and transaction number exists
//        $exists = DB::table($this->table)
//            ->where($attribute, $value)
//            ->where('id', '!=', $this->id)
//            ->where('transaction_number', '!=', $this->transactionNumber)
//            ->exists();
//
//        // If such a record exists, return false
//        if ($exists) {
//            return false;
//        }
//
//        // Existing validation logic
//        $transaction = DB::table($this->table)
//            ->where('transaction_number', $this->transactionNumber)
//            ->where('po_number', $value)
//            ->first();
//
//        if (!$transaction) {
//            return true;
//        }
//
//        if ($transaction->po_number == $value) {
//            $supplier = DB::table('suppliers')
//                ->where('id', $transaction->supplier_id)
//                ->first();
//
//            if ($supplier) {
//                return $supplier->id == $this->id;
//            }
//        }
//
//        return false;
//    }
}

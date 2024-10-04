<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountingEntries extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function initialDebit()
    {
        return $this->belongsTo(AccountTitle::class, 'initial_debit', 'id');
    }

    public function initialCredit()
    {
        return $this->belongsTo(AccountTitle::class, 'initial_credit', 'id');
    }

    public function depreciationDebit()
    {
        return $this->belongsTo(AccountTitle::class, 'depreciation_debit', 'id');
    }

    public function depreciationCredit()
    {
        return $this->belongsTo(AccountTitle::class, 'depreciation_credit', 'id');
    }

}

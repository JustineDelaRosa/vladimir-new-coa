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
        return $this->belongsTo(AccountTitle::class, 'initial_debit', 'sync_id');
    }

    public function initialCredit()
    {
        return $this->belongsTo(Credit::class, 'initial_credit', 'sync_id');
    }

    public function depreciationDebit()
    {
        return $this->belongsTo(AccountTitle::class, 'depreciation_debit', 'sync_id');
    }

    public function depreciationCredit()
    {
        return $this->belongsTo(Credit::class, 'depreciation_credit', 'sync_id');
    }

    public function secondDepreciationCredit()
    {
        return $this->belongsTo(AccountTitle::class, 'second_depreciation_credit', 'sync_id');
    }

    public function secondDepreciationDebit()
    {
        return $this->belongsTo(AccountTitle::class, 'second_depreciation_debit', 'sync_id');
    }

    public function requestContainer()
    {
        return $this->hasOne(RequestContainer::class, 'accounting_entry_id', 'id');
    }


}

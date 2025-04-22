<?php

namespace App\Models;

use App\Filters\CreditFilters;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Credit extends Model
{
    use HasFactory, Filterable;

    protected $guarded = [];

    protected string $default_filters = CreditFilters::class;

    public function accountingEntries()
    {
        return $this->hasMany(AccountingEntries::class, 'initial_credit_id', 'sync_id');
    }


}

<?php

namespace App\Models;

use App\Filters\MinorCategoryFilters;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MinorCategory extends Model
{
    use HasFactory, SoftDeletes, Filterable;

    protected $guarded = [];
    protected $hidden = [
        'updated_at',
        'created_at',
        'deleted_at'

    ];
    protected $casts = [
        'is_active' => 'boolean'
    ];

    protected string $default_filters = MinorCategoryFilters::class;

    public function majorCategory()
    {
        return $this->belongsTo(MajorCategory::class, 'major_category_id', 'id');
    }

    public function fixedAsset()
    {
        return $this->hasMany(FixedAsset::class, 'minor_category_id', 'id');
    }

    public function initialDebit()
    {
        return $this->belongsTo(AccountTitle::class, 'initial_debit_id', 'sync_id');
    }

    public function depreciationCredit()
    {
        return $this->belongsTo(Credit::class, 'depreciation_credit_id', 'sync_id');
    }

//    public function accountingEntries()
//    {
//        return $this->belongsTo(AccountingEntries::class, 'accounting_entries_id', 'id');
//    }


//    public function accountTitle(): \Illuminate\Database\Eloquent\Relations\BelongsTo
//    {
//        return $this->belongsTo(AccountTitle::class, 'account_title_sync_id', 'sync_id');
//    }

}

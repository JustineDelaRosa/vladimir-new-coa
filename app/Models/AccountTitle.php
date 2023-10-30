<?php

namespace App\Models;

use App\Filters\AccountTitleFilters;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountTitle extends Model
{
    use HasFactory, Filterable;
    protected $fillable = [
        'sync_id',
        'account_title_code',
        'is_active',
        'account_title_name'
    ];
    protected $casts = [
        'is_active' => 'boolean'
    ];

    protected string $default_filters = AccountTitleFilters::class;

    public function fixedAsset()
    {
        return $this->hasMany(FixedAsset::class, 'account_sync_id', 'sync_id');
    }
    public function minorCategory()
    {
        return $this->hasOne(MinorCategory::class, 'account_title_sync_id', 'sync_id');
    }
}

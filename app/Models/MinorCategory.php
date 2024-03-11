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
    protected $fillable = [
        'major_category_id',
        'minor_category_name',
        'account_title_sync_id',
        'is_active',
    ];
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
    public function accountTitle()
    {
        return $this->belongsTo(AccountTitle::class, 'account_title_sync_id', 'sync_id');
    }
}

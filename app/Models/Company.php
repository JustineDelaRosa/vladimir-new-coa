<?php

namespace App\Models;

use App\Filters\CompanyFilters;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory, Filterable;

    // protected $fillable = [
    //     'sync_id',
    //     'company_code',
    //     'is_active',
    //     'company_name'
    // ];
    protected $guarded = [];
    protected $casts = [
        'is_active' => 'boolean'
    ];

    protected string $default_filters = CompanyFilters::class;

    public function fixedAsset()
    {
        return $this->hasMany(FixedAsset::class, 'company_sync_id', 'sync_id');
    }

    public function department()
    {
        return $this->hasMany(Department::class, 'company_sync_id', 'sync_id');
    }

    public function businessUnit()
    {
        return $this->hasMany(BusinessUnit::class, 'company_sync_id', 'sync_id');
    }

}

<?php

namespace App\Models;

use App\Filters\BusinessUnitFilters;
use App\Filters\CompanyFilters;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessUnit extends Model
{
    use HasFactory, Filterable;

    protected $guarded = [];

    protected string $default_filters = BusinessUnitFilters::class;

    public function getCompanySyncIdAttribute($value)
    {
     $company = Company::where('sync_id', $value)->first();
        return [
            'id' => $company->id,
            'company_sync_id' => $company->sync_id,
            'company_code' => $company->company_code,
            'company_name' => $company->company_name,
            'is_active' => $company->is_active ? 1 : 0,
        ];
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_sync_id', 'sync_id');
    }

    public function departments()
    {
        return $this->hasMany(Department::class, 'business_unit_sync_id', 'sync_id');
    }
}

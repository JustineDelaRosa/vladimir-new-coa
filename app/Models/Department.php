<?php

namespace App\Models;

use App\Filters\DepartmentFilters;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory, Filterable;
    protected $fillable = [
        'sync_id',
        'company_sync_id',
        'department_code',
        'division_id',
        'is_active',
        'department_name'
    ];
    protected $casts = [
        'is_active' => 'boolean'
    ];

    protected string $default_filters = DepartmentFilters::class;

//    public function fixedAsset()
//    {
//        return $this->hasMany(FixedAsset::class, 'department_sync_id', 'sync_id');
//    }

    public function departmentUnitApprovers(){
        return $this->hasMany(DepartmentUnitApprovers::class, 'department_id', 'id');
    }

    public function subUnit(){
        return $this->hasMany(SubUnit::class,'department_id','id');
    }

    public function division()
    {
        return $this->belongsTo(Division::class, 'division_id', 'id');
    }

    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_sync_id', 'sync_id');
    }

//    public function location(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
//    {
//        return $this->belongsToMany(Location::class, 'location_sync_id', 'sync_id');
//    }
    public function location()
    {
        return $this->belongsToMany(Location::class, 'department_location',
            'department_sync_id',
            'location_sync_id',
            'sync_id',
            'sync_id');
    }

}

<?php

namespace App\Models;

use App\Filters\UnitFilters;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    use HasFactory, Filterable;

    protected $guarded = [];

    protected string $default_filters = UnitFilters::class;

    public function departments()
    {
        return $this->belongsTo(Department::class, 'department_sync_id', 'sync_id');
    }

    public function subunits()
    {
        return $this->hasMany(SubUnit::class, 'unit_sync_id', 'sync_id');
    }

    public function coordinatorHandle(){
        return $this->hasMany(CoordinatorHandle::class, 'unit_id', 'id');
    }
}

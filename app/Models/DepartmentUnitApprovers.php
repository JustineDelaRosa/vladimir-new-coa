<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Filters\DepartmentUnitApproversFilters;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class DepartmentUnitApprovers extends Model
{
    use HasFactory,Filterable;

protected $default_filters = DepartmentUnitApproversFilters::class;

    /**
     * Mass-assignable attributes.
     *
     * @var array
     */
    protected $guarded = [];

    public function department(){
        return $this->belongsTo(Department::class, 'department_id','id');
    }

    public function approver(){
        return $this->belongsTo (Approvers::class, 'approver_id','id');
    }
    public function subUnit(){
        return $this->belongsTo(SubUnit::class, 'subunit_id','id');
    }
}

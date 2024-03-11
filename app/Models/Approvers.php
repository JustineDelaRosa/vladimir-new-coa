<?php

namespace App\Models;

use App\Filters\ApproverFilters;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Approvers extends Model
{
    use HasFactory, Filterable, SoftDeletes;

    protected $guarded = [];

    protected string $default_filters = ApproverFilters::class;

    public function user()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function assetApproval()
    {
        return $this->hasMany(AssetApproval::class, 'approver_id', 'id');
    }

    public function departmentUnitApprovers()
    {
        return $this->hasMany(DepartmentUnitApprovers::class, 'approver_id', 'id');
    }
}

<?php

namespace App\Models;

use App\Filters\AssetDisposalApproverFilters;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssetDisposalApprover extends Model
{
    use HasFactory, Filterable;

    protected $guarded = [];

    protected string $default_filters = AssetDisposalApproverFilters::class;

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id', 'id');
    }

    public function subunit()
    {
        return $this->belongsTo(SubUnit::class, 'subunit_id', 'id');
    }

    public function approver()
    {
        return $this->belongsTo(Approvers::class, 'approver_id', 'id');
    }
}

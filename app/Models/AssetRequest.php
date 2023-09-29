<?php

namespace App\Models;

use App\Filters\AssetRequestFilter;
use App\Models\Status\AssetStatus;
use App\Models\Status\CycleCountStatus;
use App\Models\Status\DepreciationStatus;
use App\Models\Status\MovementStatus;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;


class AssetRequest extends Model
{
    use HasFactory, Filterable;


    protected $guarded = [];

    protected string $default_filters = AssetRequestFilter::class;

    public function currentApprover()
    {
        //pass who is the current approver of this asset request from asset approval table with status For Approval
        return $this->hasMany(AssetApproval::class , 'asset_request_id' , 'id')->where('status' , 'For Approval');
    }

//    public function approver()
//    {
//        return $this->belongsTo(Approvers::class, 'current_approver_id', 'id');
//    }

    public function assetApproval()
    {
        return $this->hasMany(AssetApproval::class, 'asset_request_id', 'id');
    }

    public function typeOfRequest()
    {
        return $this->belongsTo(TypeOfRequest::class, 'type_of_request_id', 'id');
    }

    public function capex()
    {
        return $this->belongsTo(Capex::class, 'capex_id', 'id');
    }

    public function subCapex()
    {
        return $this->belongsTo(SubCapex::class, 'sub_capex_id', 'id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requester_id', 'id');
    }

    public function division()
    {
        return $this->belongsTo(Division::class, 'division_id', 'id');
    }

    public function majorCategory()
    {
        return $this->belongsTo(MajorCategory::class, 'major_category_id', 'id');
    }

    public function minorCategory()
    {
        return $this->belongsTo(MinorCategory::class, 'minor_category_id', 'id');
    }

    public function assetStatus()
    {
        return $this->belongsTo(AssetStatus::class, 'asset_status_id', 'id');
    }

    public function cycleCountStatus()
    {
        return $this->belongsTo(CycleCountStatus::class, 'cycle_count_status_id', 'id');
    }

    public function depreciationStatus()
    {
        return $this->belongsTo(DepreciationStatus::class, 'depreciation_status_id', 'id');
    }

    public function movementStatus()
    {
        return $this->belongsTo(MovementStatus::class, 'movement_status_id', 'id');
    }

}

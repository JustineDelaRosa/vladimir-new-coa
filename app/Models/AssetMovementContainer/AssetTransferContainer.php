<?php

namespace App\Models\AssetMovementContainer;

use App\Filters\AssetTransferContainerFilters;
use App\Models\AccountTitle;
use App\Models\AssetApproval;
use App\Models\BusinessUnit;
use App\Models\Capex;
use App\Models\Company;
use App\Models\Department;
use App\Models\Division;
use App\Models\FixedAsset;
use App\Models\Location;
use App\Models\MajorCategory;
use App\Models\MinorCategory;
use App\Models\Status\AssetStatus;
use App\Models\Status\CycleCountStatus;
use App\Models\Status\DepreciationStatus;
use App\Models\Status\MovementStatus;
use App\Models\SubCapex;
use App\Models\SubUnit;
use App\Models\TypeOfRequest;
use App\Models\Unit;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class AssetTransferContainer extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, Filterable;

    protected $guarded = [];

    protected string $default_filters = AssetTransferContainerFilters::class;

    public function FixedAsset(){
        return $this->belongsTo(FixedAsset::class , 'fixed_asset_id' , 'id');
    }


    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id', 'id');
    }

    public function subunit(){
        return $this->belongsTo(SubUnit::class , 'subunit_id' , 'id');
    }
    public function businessUnit(){
        return $this->belongsTo(BusinessUnit::class , 'business_unit_id' , 'id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'id');
    }

    public function department(){
        return $this->belongsTo(Department::class , 'department_id' , 'id');
    }

    public function location(){
        return $this->belongsTo(Location::class, 'location_id', 'id');
    }
    public function accountTitle(){
        return $this->belongsTo(AccountTitle::class, 'account_id', 'id');
    }
    public function createdBy(){
        return $this->belongsTo(User::class, 'created_by_id', 'id');
    }

}

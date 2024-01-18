<?php

namespace App\Models;

use Exception;
use App\Models\User;
use App\Models\Supplier;
use App\Filters\FixedAssetFilters;
use App\Models\Status\AssetStatus;
use App\Models\Status\MovementStatus;
use App\Models\Status\CycleCountStatus;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Model;
use App\Models\Status\DepreciationStatus;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FixedAsset extends Model
{
    use HasFactory, SoftDeletes, Filterable;

    protected $guarded = [];

    // protected $casts =[

    // ]

    protected string $default_filters = FixedAssetFilters::class;


    public function additionalCost()
    {
        return $this->hasMany(AdditionalCost::class, 'fixed_asset_id', 'id');
    }
    public function capex()
    {
        return $this->belongsTo(Capex::class, 'capex_id', 'id');
    }

    public function subCapex()
    {
        return $this->belongsTo(SubCapex::class, 'sub_capex_id', 'id');
    }

    public function formula()
    {
        return $this->belongsTo(Formula::class, 'formula_id', 'id');
    }

    public function typeOfRequest()
    {
        return $this->belongsTo(TypeOfRequest::class, 'type_of_request_id', 'id');
    }

    public function majorCategory()
    {
        return $this->belongsTo(MajorCategory::class, 'major_category_id', 'id');
    }

    public function minorCategory()
    {
        return $this->belongsTo(MinorCategory::class, 'minor_category_id', 'id');
    }


    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id', 'id');
    }

    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id', 'id');
    }

    public function accountTitle()
    {
        return $this->belongsTo(AccountTitle::class, 'account_id', 'id');
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
    public function requestor()
    {
        return $this->belongsTo(User::class, 'requester_id', 'id');
    }
    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id', 'id');
    }

    public function generateWhNumber()
    {
        try {
            // Ensure the model has been saved and has an ID
            if ($this->id === null) {
                $this->save();
            }
            $warehouseNumber = $this->transaction_number + $this->id;
            // Use the ID as the reference number
            $this->wh_number = str_pad($warehouseNumber, 4, '0', STR_PAD_LEFT);

            // Save the model again to store the reference number
            $this->save();

            return $this->wh_number;
        } catch (Exception $e) {
            // Handle exception if necessary
            return null;
        }
    }
}

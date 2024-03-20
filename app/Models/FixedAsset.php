<?php

namespace App\Models;

use App\Repositories\CalculationRepository;
use Carbon\Carbon;
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
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;

class FixedAsset extends Model implements HasMedia
{
    use HasFactory, SoftDeletes, Filterable, InteractsWithMedia;

    protected $guarded = [];

    protected string $default_filters = FixedAssetFilters::class;

    public function storeBase64Image(string $base64Image, string $receiver)
    {
//        if(empty($base64Image)){
//            return;
        // Decode the base64 image data
        $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64Image));

        // Create a temporary image file
        $receiver = Str::slug($receiver);
        $fileName = $receiver . '-signature.png';
        $filePath = sys_get_temp_dir() . '/' . $fileName;
        file_put_contents($filePath, $imageData);

        // Store the image file to the Spatie Media Library
        $this->addMedia($filePath)
//        }
            ->toMediaCollection($receiver . '-signature');

        // Delete the temporary image file
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }


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

    public function warehouseNumber()
    {
        return $this->belongsTo(WarehouseNumber::class, 'warehouse_number_id', 'id');
    }
    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id', 'id');
    }

    public function subunit()
    {
        return $this->belongsTo(Subunit::class, 'subunit_id', 'id');
    }

    public function businessUnit()
    {
        return $this->belongsTo(BusinessUnit::class, 'business_unit_id', 'id');
    }

    public function getChargedDepartmentAttribute($value){
        return $value ? Department::where('id', $value)->first()->department_name : '-';
    }
    public function getStartDepreciationAttriute($value){
        return $value ? date('Y-m-d', strtotime($value)) : '-';
    }
    public function getEndDepreciationAttriute($value){
        return $value ? date('Y-m-d', strtotime($value)) : '-';
    }

    public function getDepreciationPerYearAttribute($value)
    {
        $estUsefulLife =  $this->est_useful_life;
        $scarpValue = $this->scap_value;
        $acquisitionCost = $this->acquisition_cost;

        if ($acquisitionCost == 0 && $scarpValue == 0) {
            return 0;
        }
        $estUsefulLife = floor($estUsefulLife) + (($estUsefulLife - floor($estUsefulLife)) * 12) / 12;
        return round(($acquisitionCost - $scarpValue) / $estUsefulLife, 2);
    }

    public function getDepreciationPerMonthAttribute($value): float
    {
        $estUsefulLife = $this->est_useful_life;
        $scarpValue = $this->scap_value;
        $acquisitionCost = $this->acquisition_cost;

        if ($acquisitionCost == 0 && $scarpValue == 0) {
            return 0;
        }
        $estUsefulLife = floor($estUsefulLife) * 12 + (($estUsefulLife - floor($estUsefulLife)) * 12);
        return round(($acquisitionCost - $scarpValue) / $estUsefulLife, 2);
    }
    public function getMonthsDepreciatedAttribute($value){
        return $this->start_depreciation ? Carbon::parse($this->start_depreciation)->diffInMonths(Carbon::now()) : 0;
    }

    public function getAccumulatedCostAttribute($value)
    {
        $customAge = $this->months_depreciated;
        $depreciationBasis = $this->depreciable_basis;
        $monthly_depreciation = $this->depreciation_per_month;

        $accumulated_cost = $monthly_depreciation * $customAge;
        if ($accumulated_cost > $depreciationBasis) {
            return $depreciationBasis;
        }
        return round($accumulated_cost);
    }

    public function getRemainingBookValueAttribute($value){
        $acquisitionCost = $this->acquisition_cost;
        $accumulatedCost = $this->accumulated_cost;

        $remainingBookValue = $acquisitionCost - $accumulatedCost;
        //if the remaining book value is less than zero, return zero
        if ($remainingBookValue < 0) {
            return 0;
        }
        return round($remainingBookValue);
    }

    public function getCreatedAtAttribute($value){
        return date('Y-m-d', strtotime($value));
    }


}

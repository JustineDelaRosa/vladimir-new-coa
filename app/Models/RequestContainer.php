<?php

namespace App\Models;

use App\Filters\RequestContainerFilters;
use App\Models\Status\AssetStatus;
use App\Models\Status\CycleCountStatus;
use App\Models\Status\DepreciationStatus;
use App\Models\Status\MovementStatus;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class RequestContainer extends Model implements HasMedia
{
    use HasFactory, Filterable, InteractsWithMedia, SoftDeletes;

    protected $guarded = [];

    protected string $default_filters = RequestContainerFilters::class;

    public function generateReferenceNumber(): ?string
    {
        try {
            $referenceNumber = null;

            DB::transaction(function () use (&$referenceNumber) {
                // Get last row with "FOR UPDATE" to prevent other processes from reading the same row
                $last = static::withTrashed()->latest()->lockForUpdate()->first();

                $lastId = $last ? $last->id : 0;
                $referenceNumber = str_pad($lastId + 1, 4, '0', STR_PAD_LEFT);

                // Here continue your operation, like record creation that uses the generated referenceNumber.
            });

            return $referenceNumber;
        } catch (\Exception $e) {
            // Handle exception if necessary
            return null;
        }
    }

    public static function generateTransactionNumber($requestorId): ?string
    {
        $transactionNumber = null;

        DB::transaction(function () use (&$transactionNumber) {
            $lastTransaction = AssetRequest::withTrashed()
                ->orderBy('transaction_number', 'desc')
                ->lockForUpdate()
                ->first();

            $nextNumber = $lastTransaction ? $lastTransaction->transaction_number + 1 : 1;
            $transactionNumber = str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

        });

        return $transactionNumber;
    }


    public function currentApprover()
    {
        //pass who is the current approver of this asset request from asset approval table with status For Approval
        return $this->hasMany(AssetApproval::class, 'asset_request_id', 'id')->where('status', 'For Approval');
    }

//    public function approver()
//    {
//        return $this->belongsTo(Approvers::class, 'current_approver_id', 'id');
//    }

    public function assetApproval()
    {
        return $this->hasMany(AssetApproval::class, 'transaction_number', 'transaction_number');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id', 'id');
    }

    public function subunit()
    {
        return $this->belongsTo(SubUnit::class, 'subunit_id', 'id');
    }

    public function businessUnit()
    {
        return $this->belongsTo(BusinessUnit::class, 'business_unit_id', 'id');
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

    public function requestor()
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
        return $this->belongsTo(AccountTitle::class, 'account_title_id', 'id');
    }

    public function uom()
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id', 'id');
    }

    public function smallTool()
    {
        return $this->belongsTo(SmallTools::class, 'small_tool_id', 'id');
    }

    //move all the data of requestor from request container table then pass it to asset request table then delete the request container
    public function moveAssetRequest($requestorId)
    {
        //move all item that has matching requestor id
        $assetRequest = RequestContainer::where('requester_id', $requestorId)->get();
    }



    //pass all the column of request container table to the asset request table then delete the request container
    // based it on transaction number
    public function moveToAssetRequest($transactionNumber)
    {

    }

    public function fixedAsset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class, 'fixed_asset_id', 'id');
    }

    public function receivingWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'receiving_warehouse_id', 'id');
    }
}

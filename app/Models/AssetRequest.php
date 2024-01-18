<?php

namespace App\Models;

use Exception;
use App\Filters\LocationFilters;
use Spatie\MediaLibrary\HasMedia;
use App\Models\Status\AssetStatus;
use Illuminate\Support\Facades\DB;
use App\Filters\AssetRequestFilters;
use App\Models\Status\MovementStatus;
use App\Models\Status\CycleCountStatus;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Activity;
use App\Models\Status\DepreciationStatus;
use Spatie\MediaLibrary\InteractsWithMedia;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class AssetRequest extends Model implements HasMedia
{
    use HasFactory, Filterable, InteractsWithMedia;
    //, SoftDeletes


    protected $guarded = [];

    protected string $default_filters = AssetRequestFilters::class;

    public function generateReferenceNumber(): ?string
    {
        try {
            // Ensure the model has been saved and has an ID
            if ($this->id === null) {
                $this->save();
            }

            // Use the ID as the reference number
            $this->reference_number = str_pad($this->id, 4, '0', STR_PAD_LEFT);

            // Save the model again to store the reference number
            $this->save();

            return $this->reference_number;
        } catch (\Exception $e) {
            // Handle exception if necessary
            return null;
        }
    }

    public static function generateTransactionNumber(): ?string
    {
        $transactionNumber = null;

        DB::transaction(function () use (&$transactionNumber) {
            $lastTransaction = AssetRequest::orderBy('transaction_number', 'desc')
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
        return $this->hasMany(AssetApproval::class, 'transaction_number', 'transaction_number')->where('status', 'For Approval');
    }

    //    public function approver()
    //    {
    //        return $this->belongsTo(Approvers::class, 'current_approver_id', 'id');
    //    }

    public function assetApproval()
    {
        return $this->hasMany(AssetApproval::class, 'transaction_number', 'transaction_number');
    }

    public function subunit()
    {
        return $this->belongsTo(Subunit::class, 'subunit_id', 'id');
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

    public function activityLog()
    {
        return $this->hasMany(Activity::class, 'subject_id', 'transaction_number');
    }

    public function businessunit()
    {
        return $this->belongsTo(Company::class, 'business_unit-id', 'id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id', 'id');
    }
}

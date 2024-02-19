<?php

namespace App\Models;

use Exception;
use App\Filters\LocationFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
    use HasFactory, Filterable, InteractsWithMedia, SoftDeletes;

    //, SoftDeletes


    protected $guarded = [];

    protected string $default_filters = AssetRequestFilters::class;

    public function scopeFilterByConditions(Builder $query, array $filter): Builder
    {
        $conditions = [
            'Returned' => ['status' => 'Returned'],
            'For Approval' => ['status' => ['like', 'For Approval%']],
            'For PR' => ['status' => 'Approved', 'pr_number' => null],
            'For PO' => ['status' => 'Approved', 'pr_number' => ['!=', null], 'quantity' => ['!=', DB::raw('quantity_delivered')]],
            'For Tagging' => ['status' => 'Approved', 'pr_number' => ['!=', null], 'po_number' => ['!=', null], 'print_count' => ['!=', 'quantity']],
            'For Pickup' => ['status' => 'Approved', 'pr_number' => ['!=', null], 'po_number' => ['!=', null], 'print_count' => ['=', DB::raw('quantity')]],
            'Released' => ['is_claimed' => 1],
        ];

        $query->where(function ($query) use ($filter, $conditions) {
            foreach ($filter as $key) {
                if (isset($conditions[$key])) {
                    $query->orWhere(function ($query) use ($conditions, $key) {
                        foreach ($conditions[$key] as $field => $value) {
                            if (is_array($value)) {
                                $query->where($field, $value[0], $value[1]);
                            } else {
                                $query->where($field, $value);
                            }
                        }
                    });
                }
            }
        });
        return $query;
    }

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
            $lastTransaction = AssetRequest::withTrashed()->orderBy('transaction_number', 'desc')
                ->lockForUpdate()
                ->first();

            $nextNumber = $lastTransaction ? $lastTransaction->transaction_number + 1 : 1;
            $transactionNumber = str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
        });

        return $transactionNumber;
    }


    public function currentApprover(): HasMany
    {
        //pass who is the current approver of this asset request from asset approval table with status For Approval
        return $this->hasMany(AssetApproval::class, 'transaction_number', 'transaction_number')->where('status', 'For Approval');
    }

    //    public function approver()
    //    {
    //        return $this->belongsTo(Approvers::class, 'current_approver_id', 'id');
    //    }

    public function assetApproval(): HasMany
    {
        return $this->hasMany(AssetApproval::class, 'transaction_number', 'transaction_number');
    }

    public function subunit(): BelongsTo
    {
        return $this->belongsTo(Subunit::class, 'subunit_id', 'id');
    }

    public function typeOfRequest(): BelongsTo
    {
        return $this->belongsTo(TypeOfRequest::class, 'type_of_request_id', 'id');
    }

    public function capex(): BelongsTo
    {
        return $this->belongsTo(Capex::class, 'capex_id', 'id');
    }

    public function subCapex(): BelongsTo
    {
        return $this->belongsTo(SubCapex::class, 'sub_capex_id', 'id');
    }

    public function requestor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id', 'id');
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'division_id', 'id');
    }

    public function majorCategory(): BelongsTo
    {
        return $this->belongsTo(MajorCategory::class, 'major_category_id', 'id');
    }

    public function minorCategory(): BelongsTo
    {
        return $this->belongsTo(MinorCategory::class, 'minor_category_id', 'id');
    }

    public function assetStatus(): BelongsTo
    {
        return $this->belongsTo(AssetStatus::class, 'asset_status_id', 'id');
    }

    public function cycleCountStatus(): BelongsTo
    {
        return $this->belongsTo(CycleCountStatus::class, 'cycle_count_status_id', 'id');
    }

    public function depreciationStatus(): BelongsTo
    {
        return $this->belongsTo(DepreciationStatus::class, 'depreciation_status_id', 'id');
    }

    public function movementStatus(): BelongsTo
    {
        return $this->belongsTo(MovementStatus::class, 'movement_status_id', 'id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id', 'id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id', 'id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id', 'id');
    }

    public function accountTitle(): BelongsTo
    {
        return $this->belongsTo(AccountTitle::class, 'account_title_id', 'id');
    }

    public function activityLog(): hasMany
    {
        return $this->hasMany(Activity::class, 'subject_id', 'transaction_number');
    }

    public function businessUnit(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'business_unit_id', 'id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id', 'id');
    }
    public function fixedAsset():BelongsTo
    {
        return $this->belongsTo(FixedAsset::class, 'fixed_asset_id', 'id');
    }
    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleter_id', 'id');
    }
}

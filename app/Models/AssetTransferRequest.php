<?php

namespace App\Models;

use App\Filters\AssetTransferContainerFilters;
use App\Filters\AssetTransferRequestFilters;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class AssetTransferRequest extends Model implements HasMedia
{
    use HasFactory, Filterable, InteractsWithMedia, SoftDeletes;

    protected $guarded = [];

//    protected static function booted()
//    {
//        static::deleting(function ($model) {
//            // If we are in testing environment, we prevent the deletion
//            if (app()->environment('testing') || app()->environment('local')) {
//                return false;
//            }
//        });
//    }


    protected string $default_filters = AssetTransferRequestFilters::class;

    public function setAccountableAttribute($value)
    {
        if ($value !== null && $value !== '-') {
            $parts = explode(' ', $value, 2); // Split the string into two parts
            $parts[1] = ucwords(str_replace(',', ',', strtolower($parts[1] ?? '')), " \t\r\n\f\v-"); // Apply transformation to the second part
            $value = implode(' ', $parts); // Join the parts back together
        }

        $this->attributes['accountable'] = $value;
    }

    public function fixedAsset(){
        return $this->belongsTo(FixedAsset::class, 'fixed_asset_id', 'id');
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

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id', 'id');
    }

    public function transferApproval(){
        return $this->hasMany(TransferApproval::class, 'transfer_number', 'transfer_number');
    }

    public static function generateTransferNumber()
    {
        $transferNumber = null;

        DB::transaction(function () use (&$transferNumber) {
            $lastTransfer = AssetTransferRequest::withTrashed()->orderBy('transfer_number', 'desc')
                ->lockForUpdate()
                ->first();

            $nextNumber = $lastTransfer ? $lastTransfer->transfer_number + 1 : 1;
            $transferNumber = str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
        });

        return $transferNumber;
    }
}

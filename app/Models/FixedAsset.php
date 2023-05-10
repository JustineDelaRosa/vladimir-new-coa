<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FixedAsset extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    // protected $casts =[

    // ]

    public function formula()
    {
        return $this->hasOne(Formula::class, 'fixed_asset_id', 'id');
    }

    // public function typeOfRequest()
    // {
    //     return $this->belongsTo(TypeOfRequest::class, 'type_of_request_id', 'id');
    // }

    public function majorCategory()
    {
        return $this->belongsTo(MajorCategory::class, 'major_category_id', 'id');
    }

    public function minorCategory()
    {
        return $this->belongsTo(MinorCategory::class, 'minor_category_id', 'id');
    }

    public function division()
    {
        return $this->belongsTo(Division::class, 'division_id', 'id');
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
}

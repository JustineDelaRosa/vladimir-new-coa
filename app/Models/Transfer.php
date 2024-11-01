<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Transfer extends Model implements HasMedia
{
    use HasFactory, SoftDeletes, InteractsWithMedia;

    protected $guarded = [];

    public function movementNumber()
    {
        return $this->belongsTo(MovementNumber::class, 'movement_id', 'id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'id');
    }
    public function businessUnit()
    {
        return $this->belongsTo(BusinessUnit::class, 'business_unit_id', 'id');
    }
    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id', 'id');
    }
    public function subUnit()
    {
        return $this->belongsTo(SubUnit::class, 'subunit_id', 'id');
    }
    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id', 'id');
    }
    // i also want to access the table that is connected with movement number
    public function movementApproval()
    {
        return $this->hasManyThrough(MovementApproval::class,
            MovementNumber::class,
            'id',
            'movement_id',
            'movement_id',
            'id');
    }
}

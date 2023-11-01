<?php

namespace App\Models;

use App\Filters\LocationFilters;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    use HasFactory,Filterable;
    protected $fillable = [
        'sync_id',
        'location_code',
        'is_active',
        'location_name'
    ];
    protected $casts = [
        'is_active' => 'boolean'
    ];

    protected string $default_filters = LocationFilters::class;

    public function fixedAsset()
    {
        return $this->hasMany(FixedAsset::class, 'location_sync_id', 'sync_id');
    }

//    public function departments()
//    {
//        return $this->belongsToMany(Department::class, 'location_sync_id', 'sync_id');
//    }


    public function departments()
    {
        return $this->belongsToMany(
            Department::class,
            "department_location",
            "location_sync_id",
            "department_sync_id",
            "sync_id",
            "sync_id"
        );
    }
}

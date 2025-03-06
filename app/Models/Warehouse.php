<?php

namespace App\Models;

use App\Filters\WarehouseFilters;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends Model
{
    use HasFactory, SoftDeletes, Filterable;

    protected $guarded = [];

    protected string $default_filters = WarehouseFilters::class;

    public function assetRequest()
    {
        return $this->hasMany(AssetTransferRequest::class, 'receiving_warehouse_id', 'id');
    }

    public function users()
    {
        return $this->hasMany(User::class, 'warehouse_id', 'id');
    }

    public function location()
    {
        return $this->hasMany(Location::class, 'receiving_warehouse_id', 'sync_id');
    }

    public function department()
    {
        return $this->hasMany(Department::class, 'receiving_warehouse_id', 'sync_id');
    }

//    public function warehouseLocation()
//    {
//        return $this->belongsToMany(
//            Location::class,
//            'warehouse_location',
//            'warehouse_id',
//            'location_id',
//            'sync_id',
//            'sync_id'
//        )->withTimestamps();
//    }

}

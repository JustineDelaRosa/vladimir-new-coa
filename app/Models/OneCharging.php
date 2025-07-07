<?php

namespace App\Models;

use App\Filters\OneChargingFilters;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OneCharging extends Model
{
    use HasFactory, Filterable, SoftDeletes;

    protected $guarded = [];

    protected string $default_filters = OneChargingFilters::class;

    public function requestContainer()
    {
        return $this->belongsTo(RequestContainer::class, 'request_container_id', 'id');
    }
    public function coordinatorHandle(){
        return $this->hasMany(CoordinatorHandle::class, 'one_charging_id', 'id');
    }

    public function receivingWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'receiving_warehouse_id', 'sync_id');
    }
}

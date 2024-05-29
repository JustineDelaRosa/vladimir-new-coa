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

    public function assetRequest(){
        return $this->hasMany(AssetTransferRequest::class, 'receiving_warehouse_id', 'id');
    }
}

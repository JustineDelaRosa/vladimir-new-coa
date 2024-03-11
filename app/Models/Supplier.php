<?php

namespace App\Models;

use App\Filters\SupplierFilters;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Supplier extends Model
{
    use HasFactory, Filterable;
    protected $guarded = [];
    protected $hidden = [
        'created_at',
        'updated_at'
    ];
    protected string $default_filters = SupplierFilters::class;

    public function assetRequest()
    {
        return $this->hasOne(AssetRequest::class, 'supplier_id', 'id');
    }
}

<?php

namespace App\Models\Status;

use App\Filters\AssetStatusFilters;
use App\Models\FixedAsset;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssetStatus extends Model
{
    use HasFactory, SoftDeletes, Filterable;

    protected $guarded = [];

    protected string $default_filters = AssetStatusFilters::class;

    public function fixedAssets()
    {
        return $this->hasMany(FixedAsset::class, 'asset_status_id' , 'id');
    }
}

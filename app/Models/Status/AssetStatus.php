<?php

namespace App\Models\Status;

use App\Models\FixedAsset;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssetStatus extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    public function fixedAssets()
    {
        return $this->hasMany(FixedAsset::class, 'asset_status_id' , 'id');
    }
}

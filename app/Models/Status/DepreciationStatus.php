<?php

namespace App\Models\Status;

use App\Models\FixedAsset;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DepreciationStatus extends Model
{
    use HasFactory, softDeletes;

    protected $guarded = [];

    public function fixedAssets()
    {
        return $this->hasMany(FixedAsset::class, 'depreciation_status' , 'id');
    }
}

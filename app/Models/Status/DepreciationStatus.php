<?php

namespace App\Models\Status;

use App\Models\AdditionalCost;
use App\Models\FixedAsset;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DepreciationStatus extends Model
{
    use HasFactory, softDeletes;

    protected $guarded = [];

    public function fixedAsset()
    {
        return $this->hasMany(FixedAsset::class, 'depreciation_status_id' , 'id');
    }
    public function additionalCost(){
        return $this->hasMany(AdditionalCost::class, 'depreciation_status_id' , 'id');
    }
}

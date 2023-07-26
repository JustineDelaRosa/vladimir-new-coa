<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdditionalCost extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function fixedAsset()
    {
        return $this->belongsTo(FixedAsset::class, 'fixed_asset_id', 'id');
    }
}

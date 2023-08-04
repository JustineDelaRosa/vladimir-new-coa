<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Formula extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    public function fixedAsset(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(FixedAsset::class, 'formula_id', 'id');
    }

    public function additionalCost(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(AdditionalCost::class, 'formula_id', 'id');
    }
}

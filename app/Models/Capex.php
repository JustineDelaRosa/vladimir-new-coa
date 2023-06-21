<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Capex extends Model
{
    use HasFactory, SoftDeletes;
    protected $guarded = [];

    public function fixedAsset()
    {
        return $this->hasMany(FixedAsset::class, 'capex_id', 'id');
    }
}

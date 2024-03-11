<?php

namespace App\Models;

use App\Filters\SubCapexFilters;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubCapex extends Model
{
    use HasFactory, SoftDeletes, Filterable;

    protected $guarded = [];

    protected string $default_filters = SubCapexFilters::class;

    public function capex()
    {
        return $this->belongsTo(Capex::class , 'capex_id' , 'id');
    }

    public function fixedAsset()
    {
        return $this->hasMany(FixedAsset::class, 'capex_id', 'id');
    }
}

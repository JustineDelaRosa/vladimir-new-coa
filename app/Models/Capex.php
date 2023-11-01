<?php

namespace App\Models;

use App\Filters\CapexFilters;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Capex extends Model
{
    use HasFactory, SoftDeletes, Filterable;
    protected $guarded = [];

    protected string $default_filters = CapexFilters::class;

    public function fixedAsset()
    {
        return $this->hasMany(FixedAsset::class, 'capex_id', 'id');
    }

    public function subCapex()
    {
        return $this->hasMany(SubCapex::class, 'capex_id', 'id');
    }
}

<?php

namespace App\Models;

use App\Filters\TypeOfRequestFilters;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TypeOfRequest extends Model
{
    use HasFactory, SoftDeletes, Filterable;

    protected $guarded = [];

    protected string $default_filters = TypeOfRequestFilters::class;

    public function fixedAsset()
    {
        return $this->hasMany(FixedAsset::class, 'type_of_request_id', 'id');
    }
}

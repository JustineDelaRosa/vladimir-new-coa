<?php

namespace App\Models;

use App\Filters\UnitOfMeasureFilters;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UnitOfMeasure extends Model
{
    use HasFactory, Filterable;

    protected $guarded = [];

    protected string $default_filters = UnitOfMeasureFilters::class;
}

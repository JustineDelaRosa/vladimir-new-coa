<?php

namespace App\Models;

use App\Filters\SmallToolFilters;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SmallTools extends Model
{
    use HasFactory, Filterable;
    protected string $default_filters = SmallToolFilters::class;
    protected $guarded = [];

}

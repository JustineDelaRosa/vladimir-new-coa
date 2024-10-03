<?php

namespace App\Models;

use App\Filters\ApiTokenFilters;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ApiToken extends Model
{
    use HasFactory, Filterable, SoftDeletes;


    protected string $default_filters = ApiTokenFilters::class;
    protected $guarded = [];
}

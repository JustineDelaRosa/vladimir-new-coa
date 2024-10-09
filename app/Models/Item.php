<?php

namespace App\Models;

use App\Filters\ItemFilters;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    use HasFactory, Filterable;

    protected string $default_filters = ItemFilters::class;

    protected $guarded = [];
}

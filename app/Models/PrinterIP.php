<?php

namespace App\Models;

use App\Filters\PrinterIPFilters;
use Essa\APIToolKit\Filters\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrinterIP extends Model
{
    use HasFactory, Filterable;

    protected $fillable = [
        'ip',
        'name',
        'is_active'
    ];

    protected string $default_filters = PrinterIPFilters::class;
}
